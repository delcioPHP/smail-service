<?php

namespace Cabanga\Smail;

use Cabanga\Smail\Http\Request;
use Cabanga\Smail\Http\Response;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use HTMLPurifier;
use HTMLPurifier_Config;
class ContactFormHandler {
    private const MAX_BODY_LENGTH = 4000;

    public function __construct(
        private Config $config,
        private Translator $translator,
        private Request $request
    )
    {}


    private function validateRequest(): ?Response {
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
            $this->logSuccess($this->translator->get(
                'success_send_email'));

        } catch (PHPMailerException $e) {
            $this->logError($mail->ErrorInfo);
            throw new \Exception($this->translator->get('error_sending_email'));
        }
    }

    private function logSuccess(string $message): void {
        file_put_contents($this->config->get('LOG_PATH') . 'contact.log', date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
    }

    private function logError(string $error): void {
        file_put_contents($this->config->get('LOG_PATH') . 'error.log', date('Y-m-d H:i:s') . " - Erro: $error\n", FILE_APPEND);
    }

    public function process(PHPMailer $mail): Response {
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
