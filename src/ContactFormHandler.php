<?php
/**
 * SMAIL - Secure and Configurable PHP Microservice for Email
 *
 * @author     Délcio Cabanga
 * @country    Angola
 * @created    2025/06/21
 *
 * SMAIL is a secure and configurable PHP email microservice designed to act as
 * the backend for contact forms on static websites (HTML/CSS/JS, Jamstack, Vue, React.).
 * With a single implementation, it can serve multiple websites without requiring
 * a full backend system.
 */

namespace Cabanga\Smail;

use Cabanga\Smail\Http\Request;
use Cabanga\Smail\Http\Response;
use Cabanga\Smail\interfaces\LoggerInterface;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use HTMLPurifier;
use HTMLPurifier_Config;
class ContactFormHandler {
    private const MAX_BODY_LENGTH = 4000;

    public function __construct(
        private readonly Config     $config,
        private readonly Translator $translator,
        private readonly Request    $request,
        private readonly LoggerInterface $logger
    )
    {}

    private function validateRequest(): ?Response {

        $correctKey = $this->config->get('API_SECRET_KEY');
        $submittedKey = $this->request->getHeader('X-API-Key');

        if (empty($correctKey) || !hash_equals($correctKey, $submittedKey)) {
            return $this->createErrorResponse($this->translator->get('error_unauthorized'), 403);
        }
        if ($this->request->method !== 'POST') {
            return $this->createErrorResponse($this->translator->get('error_method_not_allowed'), 405);
        }
        $allowedOrigins = explode(',', $this->config->get('ALLOWED_ORIGINS'));
        if (!in_array($this->request->origin, $allowedOrigins)) {
            return $this->createErrorResponse($this->translator->get('error_unauthorized'), 403);
        }
        if ($this->request->contentType !== 'application/json') {
            return $this->createErrorResponse($this->translator->get('error_content_type'), 400);
        }
        if (!empty($this->request->get('websiteUrl'))) {
            $body = json_encode([
                'success' => true,
                'message' => $this->translator->get('generic_success')
            ]);
            echo $body;
            exit();
        }
        return null;
    }

    private function validateData(): ?Response {
        $isCustomTemplate = !empty($this->request->get('html_template'));
        $defaultRequiredFields = $isCustomTemplate ?
            ['name', 'email'] : ['name', 'email', 'query'];
        $requiredFields = $this->request->get('required_fields', $defaultRequiredFields);

        foreach ($requiredFields as $field) {
            if (!$this->request->get($field)) {
                return $this->createErrorResponse(
                    $this->translator->get('error_field_required', [$field]),
                    400
                );
            }
        }

        if (!filter_var($this->request->get('email'),
            FILTER_VALIDATE_EMAIL)
        ) {
            return $this->createErrorResponse($this->translator->get(
                'error_invalid_email'), 400
            );
        }

        if (strlen($this->request->get('query', '')) >
            self::MAX_BODY_LENGTH
        ) {
            return $this->createErrorResponse($this->translator->get(
                'error_message_too_long'), 400
            );
        }

        return null;
    }

    private function sanitizeData(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    private function renderTemplate(string $templatePath, array $data): string {
        ob_start();
        extract($data);
        include $templatePath;
        return ob_get_clean();
    }

    private function buildEmailContent(array $data): string {
        if (!empty($data['html_template'])) {
            //ATT - SEGURANÇA: Purificar o HTML recebido
            $purifierConfig = HTMLPurifier_Config::createDefault();
            $purifier = new HTMLPurifier($purifierConfig);
            $cleanHtml = $purifier->purify($data['html_template']);
            // chnge placeholders {{name}}, {{email}} etc.
            foreach ($data as $key => $value) {
                if (is_scalar($value)) {
                    $cleanHtml = str_replace("{{{$key}}}", nl2br($value), $cleanHtml);
                }
            }
            return $cleanHtml;
        }

        //Use default template
        $templatePath = __DIR__ . '/../templates/default-template.php';
        return $this->renderTemplate($templatePath, $data);
    }

    /**
     * @throws \Exception
     */
    private function sendEmail(array $data, PHPMailer $mail): void {
        try {
            $mail->isSMTP();
            $mail->Host = $this->config->get('SMTP_HOST');
            $mail->SMTPAuth = true;
            $mail->Username = $this->config->get('SMTP_USERNAME');
            $mail->Password = $this->config->get('SMTP_PASSWORD');

            if ($this->config->get('SMTP_SECURE') === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($this->config->get('SMTP_SECURE') === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }

            $mail->Port = (int)$this->config->get('SMTP_PORT');
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($this->config->get('EMAIL_FROM'), $this->config->get('EMAIL_FROM_NAME'));
            $mail->addAddress($this->config->get('EMAIL_TO'));
            $mail->addReplyTo($data['email'], $data['name']);

            $mail->isHTML(true);
            $mail->Subject = $this->config->get('EMAIL_SUBJECT');
            $mailBody = $this->buildEmailContent($data);
            $mail->Body = $mailBody;
            $mail->AltBody = strip_tags($mailBody);

            $mail->send();
            //Lembrete DC, pensar em salvar os mails nos logs, mas de forma incompleta para segura
//            E-mail enviado com sucesso de {$data['email']}
            $this->logger->logSuccess($this->translator->get(
                'success_message'));

        } catch (PHPMailerException $e) {
            $this->logger->logError($mail->ErrorInfo);
            throw new \Exception($this->translator->get('error_sending_email'));
        }
    }

    private function validateRecaptcha(): ?Response
    {
        $token = $this->request->get('recaptchaToken');

        if (empty($token)) {
            return $this->createErrorResponse($this->translator->get('error_recaptcha'), 400);
        }

        $secretKey = $this->config->get('RECAPTCHA_SECRET_KEY');
        $url  = $this->config->get('RECAPTCHA_URL');
        $data = [
            'secret'   => $secretKey,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ];

        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        $context  = stream_context_create($options);
        $responseJson = @file_get_contents($url, false, $context);

        if ($responseJson === false) {
            $this->logger->logError($this->translator->get('error_recaptcha_link'));
            return $this->createErrorResponse($this->translator->get('error_recaptcha_link'), 500);
        }

        $response = json_decode($responseJson, true);
        $threshold = (float) $this->config->get('RECAPTCHA_V3_THRESHOLD', '0.5');

        if (!$response['success'] || $response['score'] < $threshold) {
            $this->logger->logError($this->translator->get('error_recaptcha_reject'). 'Score: ' . ($response['score'] ?? 'N/A') . '. Errors: ' . implode(', ', $response['error-codes'] ?? []));
            return $this->createErrorResponse($this->translator->get('error_recaptcha_reject'), 403);
        }
        return null;
    }

    public function process(PHPMailer $mail): Response {

        if ($this->config->get('RECAPTCHA_ENABLED') === 'true') {
            if ($response = $this->validateRecaptcha()) {
                return $response;
            }
        }

        if ($response = $this->validateRequest()) return $response;
        if ($response = $this->validateData()) return $response;

        $sanitizedData = $this->sanitizeData($this->request->body);

        try {
            $this->sendEmail($sanitizedData, $mail);
        } catch (\Exception $e) {
            return $this->createErrorResponse($e->getMessage(), 500);
        }
        $body = json_encode([
            'success' => true,
            'message' => $this->translator->get('success_message')
        ]);
        return new Response($body, 200, [
            "Access-Control-Allow-Origin" => $this->request->origin,
            "Content-Type" => "application/json"
        ]);
    }

    private function createErrorResponse(
        string $message, int $statusCode
    ): Response
    {
        $body = json_encode(['success' => false, 'message' => $message]);
        return new Response($body, $statusCode, [
            "Access-Control-Allow-Origin" => $this->request->origin,
            "Content-Type" => "application/json"
        ]);
    }
}
