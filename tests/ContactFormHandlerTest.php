<?php

namespace Tests;

use Cabanga\Smail\interfaces\HttpClientInterface;
use Cabanga\Smail\interfaces\LoggerInterface;
use PHPUnit\Framework\TestCase;
use Cabanga\Smail\Config;
use Cabanga\Smail\Translator;
use Cabanga\Smail\Http\Request;
use Cabanga\Smail\ContactFormHandler;
use PHPMailer\PHPMailer\PHPMailer;

class ContactFormHandlerTest extends TestCase
{
    private $configMock;
    private $translatorMock;
    private $phpMailerMock;
    private $loggerMock;
    private $httpClientMock;

    protected function setUp(): void
    {
        //Mocks - Coisa chata like ET's
        $this->configMock = $this->createMock(Config::class);
        $this->translatorMock = $this->createMock(Translator::class);
        $this->phpMailerMock = $this->createMock(PHPMailer::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->httpClientMock = $this->createMock(HttpClientInterface::class);
    }

    public function testProcessSuccess()
    {
        $body = [
            'name' => 'DC',
            'email' => 'test@dc.ao',
            'query' => 'This is a test',
            'websiteUrl'=>''
        ];

        $testApiKey = 'bfd8669e46489869bdbd0ffad6dea8af13aa6e068936c35233fb1aa5d6ce9e9';
        $headers = ['X-API-Key' => $testApiKey];
        $this->configMock->method('get')->willReturnMap([
            ['ALLOWED_ORIGINS', null, 'http://localhost'],
            ['SMTP_HOST', null, 'smtp.dc.ao'],
            ['SMTP_PORT', null, 587],
            ['SMTP_USERNAME', null, 'dc'],
            ['SMTP_PASSWORD', null, 'pass'],
            ['SMTP_SECURE', null, 'tls'],
            ['EMAIL_FROM', null, 'from@dc.ao'],
            ['EMAIL_FROM_NAME', null, 'Test From'],
            ['EMAIL_TO', null, 'dc@dc.ao'],
            ['EMAIL_SUBJECT', null, 'Test Subject'],
            ['DEFAULT_LANG', null, 'en'],
            ['RECAPTCHA_ENABLED', null, false],
            ['API_SECRET_KEY', null, $testApiKey],
        ]);
        $request = new Request(
            'POST', 'http://localhost',
            'application/json',
            $body,
            $headers
        );

        $this->translatorMock->method('get')
            ->with('success_message')
            ->willReturn('Message sent successfully!');

        $this->phpMailerMock->expects($this->once())->method('send');

        $this->loggerMock->expects($this->once())->method('logSuccess');
        $this->loggerMock->expects($this->never())->method('logError');

        $service = new ContactFormHandler(
            $this->configMock,
            $this->translatorMock,
            $request,
            $this->loggerMock,
            $this->httpClientMock
        );
        $response = $service->process($this->phpMailerMock);
        //var_dump($request);
        $this->assertEquals(200, $response->statusCode);
        $this->assertJsonStringEqualsJsonString(
            json_encode(['success' => true, 'message' => 'Message sent successfully!']),
            $response->body
        );
    }


//    public function testProcessSuccess()
//    {
//        $body = [
//            'name' => 'DC',
//            'email' => 'test@dc.ao',
//            'query' => 'This is a test',
//            'websiteUrl' => ''
//        ];
//
//        $testApiKey = 'bfd8669e46489869bdbd0ffad6dea8af13aa6e068936c35233fb1aa5d6ce9e9';
//        $headers = ['X-API-Key' => $testApiKey];
//
//        $this->configMock->method('get')->willReturnMap([
//            ['API_SECRET_KEY', null, $testApiKey],
//            ['ALLOWED_ORIGINS', null, 'http://localhost'],
//            ['RECAPTCHA_ENABLED', null, 'false'],
//            ['SMTP_HOST', null, 'smtp.dc.ao'],
//            ['SMTP_PORT', null, 587],
//            ['SMTP_USERNAME', null, 'dc'],
//            ['SMTP_PASSWORD', null, 'pass'],
//            ['SMTP_SECURE', null, 'tls'],
//            ['EMAIL_FROM', null, 'from@dc.ao'],
//            ['EMAIL_FROM_NAME', null, 'Test From'],
//            ['EMAIL_TO', null, 'dc@dc.ao'],
//            ['EMAIL_SUBJECT', null, 'Test Subject'],
//            ['DEFAULT_LANG', null, 'en'],
//        ]);
//
//        $request = new Request(
//            'POST',
//            'http://localhost',
//            'application/json',
//            $body,
//            $headers
//        );
//
//        $this->translatorMock->method('get')
//            ->with('success_message')
//            ->willReturn('Message sent successfully!');
//
//        $this->phpMailerMock->expects($this->once())->method('isSMTP');
//        $this->phpMailerMock->expects($this->once())->method('setFrom')->with('from@dc.ao', 'Test From');
//        $this->phpMailerMock->expects($this->once())->method('addAddress')->with('dc@dc.ao');
//        $this->phpMailerMock->expects($this->once())->method('addReplyTo')->with('test@dc.ao', 'DC');
//        $this->phpMailerMock->expects($this->once())->method('isHTML')->with(true);
//
//        $this->phpMailerMock->expects($this->once())->method('send');
//
//        $this->loggerMock->expects($this->once())->method('logSuccess');
//        $this->loggerMock->expects($this->never())->method('logError');
//
//        $service = new ContactFormHandler(
//            $this->configMock,
//            $this->translatorMock,
//            $request,
//            $this->loggerMock
//        );
//        $response = $service->process($this->phpMailerMock);
//
//        $this->assertEquals(200, $response->statusCode);
//        $this->assertJsonStringEqualsJsonString(
//            json_encode(['success' => true, 'message' => 'Message sent successfully!']),
//            $response->body
//        );
//    }

    public function testProcessFailsWhenRequiredFieldIsMissing()
    {
        $testApiKey = 'bfd8669e46489869bdbd0ffad6dea8af13aa6e068936c35233fb1aa5d6ce9e9';
        $headers = ['X-API-Key' => $testApiKey];
        $body = [
            'name' => 'Test User',
            'query' => 'No email here',
            'websiteUrl'=>''
        ];

        $this->configMock->method('get')->willReturnMap([
            ['ALLOWED_ORIGINS', null, 'http://localhost'],
            ['SMTP_HOST', null, 'smtp.dc.ao'],
            ['SMTP_PORT', null, 587],
            ['SMTP_USERNAME', null, 'dc'],
            ['SMTP_PASSWORD', null, 'pass'],
            ['SMTP_SECURE', null, 'tls'],
            ['EMAIL_FROM', null, 'from@dc.ao'],
            ['EMAIL_FROM_NAME', null, 'Test From'],
            ['EMAIL_TO', null, 'dc@dc.ao'],
            ['EMAIL_SUBJECT', null, 'Test Subject'],
            ['DEFAULT_LANG', null, 'pt'],
            ['RECAPTCHA_ENABLED', null, false],
            ['API_SECRET_KEY', null, $testApiKey],
        ]);

        $request = new Request(
            'POST',
            'http://localhost',
            'application/json',
            $body,
            $headers
        );

        $this->configMock->method('get')->willReturn('http://localhost');
        $this->translatorMock->method('get')
            ->with('error_field_required', ['email'])
            ->willReturn('Field email is required');

        $this->phpMailerMock->expects($this->never())->method('send');
        $this->loggerMock->expects($this->never())->method('logSuccess');

        $service = new ContactFormHandler(
            $this->configMock,
            $this->translatorMock,
            $request,
            $this->loggerMock,
            $this->httpClientMock
        );
        $response = $service->process($this->phpMailerMock);

        $this->assertEquals(400, $response->statusCode);
        $this->assertJsonStringEqualsJsonString(
            json_encode(['success' => false, 'message' => 'Field email is required']),
            $response->body
        );
    }


    public function testProcessSucceedsWithValidRecaptcha()
    {
        $testApiKey = 'bfd8669e46489869bdbd0ffad6dea8af13aa6e068936c35233fb1aa5d6ce9e9';
        $headers = ['X-API-Key' => $testApiKey];
        $body = [
            'name' => 'Ser Humano Verificado',
            'email' => 'humano@test.ao',
            'query' => 'ok',
            'recaptchaToken' => 'AfsjasgsaTYWNASa',
            'websiteUrl'=>''
        ];

        $request = new Request(
            'POST',
            'http://localhost',
            'application/json',
            $body,
            $headers
        );

        $this->configMock->method('get')->willReturnMap([
            ['ALLOWED_ORIGINS', null, 'http://localhost'],
            ['SMTP_HOST', null, 'smtp.dc.ao'],
            ['SMTP_PORT', null, 587],
            ['SMTP_USERNAME', null, 'dc'],
            ['SMTP_PASSWORD', null, 'pass'],
            ['SMTP_SECURE', null, 'tls'],
            ['EMAIL_FROM', null, 'from@dc.ao'],
            ['EMAIL_FROM_NAME', null, 'Test From'],
            ['EMAIL_TO', null, 'dc@dc.ao'],
            ['EMAIL_SUBJECT', null, 'Test Subject'],
            ['DEFAULT_LANG', null, 'en'],
            ['RECAPTCHA_ENABLED', null, true],
            ['API_SECRET_KEY', null, $testApiKey],
            ['RECAPTCHA_SECRET_KEY', null, 'AfsjasgsaTYWNASa'],
            ['RECAPTCHA_V3_THRESHOLD', null, '0.5'],
            ['RECAPTCHA_URL', null, 'https://www.google.com/recaptcha/api/siteverify'],
        ]);

        $googleResponse = json_encode(['success' => true, 'score' => 0.9]);
        $this->httpClientMock
            ->expects($this->once())
            ->method('post')
            ->willReturn($googleResponse);

        $this->phpMailerMock->expects($this->once())->method('send');

        $service = new ContactFormHandler(
            $this->configMock,
            $this->translatorMock,
            $request,
            $this->loggerMock,
            $this->httpClientMock
        );
        $response = $service->process($this->phpMailerMock);
        //var_dump($response);
        $this->assertEquals(200, $response->statusCode);
    }


    public function testProcessFailsWithLowScoreRecaptcha()
    {
        $testApiKey = 'bfd8669e46489869bdbd0ffad6dea8af13aa6e068936c35233fb1aa5d6ce9e9';
        $headers = ['X-API-Key' => $testApiKey];
        $body = [
            'name' => 'Posssível Bot',
            'email' => 'humano@test.ao',
            'query' => 'ok',
            'recaptchaToken' => 'AfsjasgsaTYWNASa',
            'websiteUrl'=>''
        ];

        $request = new Request(
            'POST',
            'http://localhost',
            'application/json',
            $body,
            $headers
        );

        $this->configMock->method('get')->willReturnMap([
            ['ALLOWED_ORIGINS', null, 'http://localhost'],
            ['SMTP_HOST', null, 'smtp.dc.ao'],
            ['SMTP_PORT', null, 587],
            ['SMTP_USERNAME', null, 'dc'],
            ['SMTP_PASSWORD', null, 'pass'],
            ['SMTP_SECURE', null, 'tls'],
            ['EMAIL_FROM', null, 'from@dc.ao'],
            ['EMAIL_FROM_NAME', null, 'Test From'],
            ['EMAIL_TO', null, 'dc@dc.ao'],
            ['EMAIL_SUBJECT', null, 'Test Subject'],
            ['DEFAULT_LANG', null, 'en'],
            ['RECAPTCHA_ENABLED', null, true],
            ['API_SECRET_KEY', null, $testApiKey],
            ['RECAPTCHA_SECRET_KEY', null, 'AfsjasgsaTYWNASa'],
            ['RECAPTCHA_V3_THRESHOLD', null, '0.5'],
            ['RECAPTCHA_URL', null, 'https://www.google.com/recaptcha/api/siteverify'],
        ]);

        $googleResponse = json_encode(['success' => false, 'score' => 0.2]);
        $this->httpClientMock->expects(
            $this->once())->method('post')->willReturn($googleResponse);

        $this->translatorMock->method('get')
            ->with('error_recaptcha_reject')
            ->willReturn('reCAPTCHA API rejected');

        $this->phpMailerMock->expects($this->never())->method('send');

        $service = new ContactFormHandler(
            $this->configMock,
            $this->translatorMock,
            $request,
            $this->loggerMock,
            $this->httpClientMock
        );
        $response = $service->process($this->phpMailerMock);
        var_dump($response);

        $this->assertEquals(403, $response->statusCode);
        $expectedJsonBody = json_encode(['success' => false, 'message' => 'reCAPTCHA API rejected']);
        $this->assertJsonStringEqualsJsonString($expectedJsonBody, $response->body);
    }
}