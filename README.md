
# Smail (Simple Mail Service) - Email Microservice for Receiving Contact Form Submissions

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg)](https://php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docker Ready](https://img.shields.io/badge/docker-ready-blue.svg)](https://www.docker.com/)
[![Tests](https://img.shields.io/badge/testes-passing-brightgreen.svg)]()

**Smail** is a secure and configurable PHP email microservice designed to serve as the backend for contact forms on static websites (HTML/CSS/JS, VueJS, React, etc.). With a single implementation, it can support multiple websites without the need for a full backend.

This service was built with a focus on security, flexibility, and ease of implementation.

## Features

- **Simple Integration**: A single endpoint to receive form data via JSON.
- **Centralized Configuration**: All configurations (SMTP, emails, routes) are controlled through a `.env` file or Docker environment variables.
- **Security**:
    - CORS protection to restrict allowed domains.
    - Input validation, including conditional logic for required fields.
    - Sanitization of all inputs to prevent XSS.
    - HTML template purification to block malicious code.
- **Dynamic Templates**: Use the default email template or send fully custom HTML per request.
- **Multilingual Responses**: Returns success or error messages in the requested language (English and Portuguese by default, easily extendable).
- **Logging**: Logs successful submissions and errors in separate log files.
- **Tested**: Includes a PHPUnit test suite to ensure reliability.
- **Docker Ready**: Comes with a `docker-compose.yml` for instant and isolated deployment.

## Requirements

- PHP >= 8.1
- [Composer](https://getcomposer.org/)
- [Docker](https://www.docker.com/) (Recommended for deployment)

## Installation & Deployment

### With Docker (Recommended)

1. Create a file named `docker-compose.yml` and paste the following content. Make sure to use the latest image version (or download it from the repository).

```yaml
version: '3.8'

services:
  smail-service:
    image: cabanga/smail-service:1.0.0
    container_name: smail-service
    ports:
      - "8080:80"
    restart: unless-stopped
    environment:
      # SERVICE
      - ALLOWED_ORIGINS=http://localhost:3000,http://yourdomain.ao
      - API_ROUTE=/api/contact
      - LOG_PATH=/var/www/html/logs/
      - DEFAULT_LANG=pt
      - API_SECRET_KEY=chave_super_secreta

      # SMTP
      - SMTP_HOST=smtp.yourdomain.ao
      - SMTP_PORT=465
      - SMTP_USERNAME=no-reply@yourdomain.ao
      - SMTP_PASSWORD=SuaSenhaSuperSegura
      - SMTP_SECURE=ssl

      # E-mail
      - EMAIL_FROM=no-reply@yourdomain.ao
      - EMAIL_FROM_NAME="Contacto do Site"
      - EMAIL_TO=your@yourdomain.com
      - EMAIL_SUBJECT="Novo Contacto do Website"

      # SECURITY  
      - RECAPTCHA_ENABLED=false  
      - RECAPTCHA_SECRET_KEY=''  
      - RECAPTCHA_V3_THRESHOLD='0.5'
      - RECAPTCHA_URL='https://www.google.com/recaptcha/api/siteverify'
```

2. **Edit Environment Variables:** Adjust the values in the `environment:` section to match your SMTP credentials, allowed domains, etc.

3. **Start the Service:** In the same folder where you saved the file, run:

```bash
docker-compose up -d
```

4. **Check if it works:** The service should now be accessible at `http://localhost:8080`.

5. To stop the service, run:

```bash
docker-compose down
```

### Manual Installation

1. **Clone the repository:**

```bash
git clone https://github.com/delcioPHP/smail-service
cd smail-service
```

2. **Install dependencies:**

```bash
composer install --no-dev --optimize-autoloader
```

3. **Configure the environment:**

```bash
cp .env.example .env
```

Edit the `.env` file with your custom configuration.

4. **Configure Web Server:**

Point the root of your web server (Apache, Nginx) to the `/public` folder. Make sure URL rewrites (mod_rewrite) are enabled.

### Manual via Composer

Using Composer, just run one command and follow up with web server setup:

```bash
composer create-project cabanga/smail-service smail-service
```

## Usage (Service)

### Endpoint

- **URL:** `[YOUR_DOMAIN]` + `[API_ROUTE]` (e.g. `http://localhost:8080/api/contact`)
- **Method:** `POST`
- **Headers:** `Content-Type: application/json` and `'X-API-Key': apiKey`

### Request Body (JSON)

#### Basic Use

Uses the default email template. The `query` field is required.

```json
{
  "name": "Kubanza Nzagi Afric",
  "email": "kubanza.nzagi@cliente.com",
  "recaptchaToken": "token",
  "company": "Empresa Fantástica Lda",
  "query": "Olá, gostaria de pedir um orçamento para o vosso serviço.",
  "websiteUrl": ""
}
```

#### Advanced Use

Allows customized behavior.

```json
{
  "lang": "en",
  "websiteUrl": "",
  "recaptchaToken": "token",
  "name": "Kubanza Nzagi Afric",
  "email": "kubanza.nzagi@client.com",
  "phone": "555-1234",
  "subject": "Urgent Quote Request",
  "required_fields": ["name", "email", "subject"],
  "html_template": "<h1>New Lead: {{subject}}</h1><p>From: {{name}} ({{email}}).</p><p>Phone: {{phone}}</p>"
}
```

- `lang` (optional): Sets the API response language. Values: `"en"`, `"pt"`.
- `html_template` (optional): An HTML string for the email body. If used, `query` is not required. Use `{{field_name}}` placeholders.
- `required_fields` (optional): Array of fields that must be present in this request.

### API Responses

#### Success (200 OK)

``en:``

```json

{
  "success": true,
  "message": "Message sent successfully!"
}
```

``pt:``

```json

{
  "success": true,
  "message": "Mensagem enviada com sucesso!"
}
```

#### Error (4xx or 5xx)

``en:``
```json
{
  "success": false,
  "message": "The field email is required"
}
```

``pt:``
```json
{
  "success": false,
  "message": "O campo email é obrigatório"
}
```
- **400 Bad Request:** Missing data, invalid JSON.
- **403 Forbidden:** Unauthorized domain access.
- **404 Not Found:** Endpoint not found.
- **405 Method Not Allowed:** Incorrect HTTP method.
- **500 Internal Server Error:** Internal failure (e.g. wrong SMTP credentials).

### JavaScript Example

```javascript
const apiKey = 'chave_super_secreta';

fetch('https://api.seusite.com/api/contact', {
  method: 'POST',
  headers: { 
    'Content-Type': 'application/json',
    'X-API-Key': apiKey
  },
  body: JSON.stringify({
    name: "Kubanza Nzagi Afric",
    email: "kubanza@afric.ao",
    query: "A minha mensagem segura.",
    recaptchaToken: "token",
    websiteUrl: ""
  })
})
.then(res => res.json())
.then(data => console.log(data));
```

### CRITICAL SECURITY WARNING

The API key should **NEVER** be hardcoded directly in your public JavaScript file. Anyone can view the source code and steal it.

**Best practice?** Inject the key into your frontend using **client-side environment variables**. Your build process should then securely make it available to your JavaScript.

To further enhance security, you may enable Google reCAPTCHA. Smail supports it out-of-the-box.

Examples:

- `process.env.VUE_APP_API_KEY` (Vue.js)
- `process.env.REACT_APP_API_KEY` (React)

## License

This project is licensed under the MIT License.

WARNING: Modifications require authorization.
