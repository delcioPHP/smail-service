<?php

namespace Cabanga\Smail;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use HTMLPurifier;
use HTMLPurifier_Config;
class ContactFormHandler {
    private Config $config;
    private Translator $translator;
    private array $data;
    private string $origin;
    private const MAX_BODY_LENGTH = 4000;

    public function __construct(Config $config) {
        $this->config = $config;
        $this->origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $this->data = $this->getInputData();
        $lang = $this->data['lang'] ?? $this->config->get(
            'DEFAULT_LANG', 'en'
        );
        $this->translator = new Translator(
            $lang, $this->config->get('DEFAULT_LANG', 'en')
        );
        $this->validateRequest();
    }

    private function validateRequest(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendErrorResponse($this->translator->get(
                'error_method_not_allowed'), 405
            );
        }
        $allowedOrigins = explode(',', $this->config->get(
            'ALLOWED_ORIGINS')
        );
        if (!in_array($this->origin, $allowedOrigins)) {
            $this->sendErrorResponse($this->translator->get(
                'error_unauthorized'), 403
            );
        }
        if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
            $this->sendErrorResponse($this->translator->get(
                'error_content_type'), 400
            );
        }
    }

    private function setCorsHeaders(): void {
        header("Access-Control-Allow-Origin: {$this->origin}");
        header("Access-Control-Allow-Methods: POST");
        header("Access-Control-Allow-Headers: Content-Type");
    }

    private function getInputData(): array {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
            exit;
        }
        return $data;
    }

    private function validateData(): void {
        $requiredFields = $this->data['required_fields'] ?? ['name', 'email', 'query'];
        foreach ($requiredFields as $field) {
            if (empty($this->data[$field])) {
                $errorMessage = $this->translator->get(
                    'error_field_required', [$field]
                );
                $this->sendErrorResponse($errorMessage, 400);
            }
        }
        if (!filter_var($this->data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->sendErrorResponse($this->translator->get(
                'error_invalid_email'), 400
            );
        }
        if (
            isset($this->data['query']) &&
            strlen($this->data['query']) > self::MAX_BODY_LENGTH
        ) {
            $this->sendErrorResponse($this->translator->get(
                'error_message_too_long'), 400
            );
        }
    }

    private function sanitizeData(): array {
        $sanitized = [];

        foreach ($this->data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
            } else {
                $sanitized[$key] = $value; // Mantém outros tipos de dados
            }
        }
        return $sanitized;
    }

    private function renderTemplate(string $templatePath, array $data): string {
        ob_start();
        // Torna os dados disponíveis para o template
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

    private function sendEmail(array $data): void {
        $mail = new PHPMailer(true);

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
            $this->sendErrorResponse($this->translator->get(
                'error_sending_email'), 500
            );
        }
    }

    private function logSuccess(string $message): void {
        file_put_contents($this->config->get('LOG_PATH') . 'contact.log', date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
    }

    private function logError(string $error): void {
        file_put_contents($this->config->get('LOG_PATH') . 'error.log', date('Y-m-d H:i:s') . " - Erro: $error\n", FILE_APPEND);
    }

    private function sendErrorResponse(string $message, int $statusCode): void {
        http_response_code($statusCode);
        $this->setCorsHeaders();
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }

    public function process(): void {
        $this->setCorsHeaders();
        $this->validateData();
        $sanitizedData = $this->sanitizeData();
        $this->sendEmail($sanitizedData);

        echo json_encode([
            'success' => true,
            'message' => $this->translator->get('success_message')
        ]);
    }
}
