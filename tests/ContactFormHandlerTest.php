<?php

namespace Tests;

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

    protected function setUp(): void
    {
        //Mocks - Coisa chata
        $this->configMock = $this->createMock(Config::class);
        $this->translatorMock = $this->createMock(Translator::class);
        $this->phpMailerMock = $this->createMock(PHPMailer::class);
    }

    public function testProcessSuccess()
    {
        $body = [
            'name' => 'DC',
            'email' => 'test@dc.ao',
            'query' => 'This is a test'
        ];
        $request = new Request('POST', 'http://localhost', 'application/json', $body);

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
            ['LOG_PATH', '../logs/', '../logs/']
        ]);

        $this->translatorMock->method('get')
            ->with('success_message')
            ->willReturn('Email sent!');

        $this->phpMailerMock->expects($this->once())->method('send');

        //Actions
        $service = new ContactFormHandler($this->configMock, $this->translatorMock, $request);
        $response = $service->process($this->phpMailerMock);

        //Assert (Verificar)
        $this->assertEquals(200, $response->statusCode);
        $this->assertJsonStringEqualsJsonString(
            json_encode(['success' => true, 'message' => 'Email sent!']),
            $response->body
        );
    }

    public function testProcessFailsWhenRequiredFieldIsMissing()
    {
        $body = ['name' => 'Test User'];
        $request = new Request('POST', 'http://localhost', 'application/json', $body);

        $this->configMock->method('get')->willReturn('http://localhost');
        $this->translatorMock->method('get')
            ->with('error_field_required', ['email'])
            ->willReturn('Field email is required');

        $this->phpMailerMock->expects($this->never())->method('send');

        $service = new ContactFormHandler($this->configMock, $this->translatorMock, $request);
        $response = $service->process($this->phpMailerMock);

        $this->assertEquals(400, $response->statusCode);
        $this->assertJsonStringEqualsJsonString(
            json_encode(['success' => false, 'message' => 'Field email is required']),
            $response->body
        );
    }
}