<?php

declare(strict_types=1);

namespace App\Core;

final class ServerRequest
{
    private array $server;
    private array $query;
    private array $post;
    private array $files;
    private array $cookies;
    private ?string $body;
    private string $sessionPrefix;

    public function __construct(
        ?array $server = null,
        ?array $query = null,
        ?array $post = null,
        ?array $files = null,
        ?array $cookies = null,
        string $sessionPrefix = 'flasherpsystem'
    ) {
        $this->server = $server ?? $_SERVER;
        $this->query = $query ?? $_GET;
        $this->post = $post ?? $_POST;
        $this->files = $files ?? $_FILES;
        $this->cookies = $cookies ?? $_COOKIE;
        $this->body = null;
        $this->sessionPrefix = $sessionPrefix;
    }

    public function getMethod(): string
    {
        return strtoupper((string)($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    public function isPost(): bool
    {
        return $this->getMethod() === 'POST';
    }

    public function isGet(): bool
    {
        return $this->getMethod() === 'GET';
    }

    public function isAjax(): bool
    {
        return strtolower((string)($this->server['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    public function getUri(): string
    {
        return (string)($this->server['REQUEST_URI'] ?? '/');
    }

    public function getClientIp(): string
    {
        return (string)($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function getUserAgent(): string
    {
        return (string)($this->server['HTTP_USER_AGENT'] ?? 'unknown');
    }

    public function getReferrer(): string
    {
        return (string)($this->server['HTTP_REFERER'] ?? '');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function hasPost(string $key): bool
    {
        return isset($this->post[$key]);
    }

    public function hasQuery(string $key): bool
    {
        return isset($this->query[$key]);
    }

    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) && $this->files[$key]['error'] !== UPLOAD_ERR_NO_FILE;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function allPost(): array
    {
        return $this->post;
    }

    public function allQuery(): array
    {
        return $this->query;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function getRawBody(): string
    {
        if ($this->body === null) {
            $this->body = (string)file_get_contents('php://input');
        }
        return $this->body;
    }

    public function getJsonBody(): ?array
    {
        $contentType = (string)($this->server['HTTP_CONTENT_TYPE'] ?? $this->server['CONTENT_TYPE'] ?? '');
        if (stripos($contentType, 'application/json') === false) {
            return null;
        }
        $decoded = json_decode($this->getRawBody(), true);
        return is_array($decoded) ? $decoded : null;
    }

    public function getCsrfToken(): string
    {
        $token = $this->post('csrf_token') ?: $this->server('HTTP_X_CSRF_TOKEN');
        return is_string($token) ? $token : '';
    }

    public function validateCsrfToken(): bool
    {
        if (!isset($_SESSION[$this->sessionPrefix]['DASHBOARD']['csrf_token'])) {
            return false;
        }

        $provided = $this->getCsrfToken();
        if ($provided === '') {
            return false;
        }

        return hash_equals($_SESSION[$this->sessionPrefix]['DASHBOARD']['csrf_token'], $provided);
    }

    public function getSessionUserId(): ?int
    {
        $id = $_SESSION[$this->sessionPrefix]['DASHBOARD']['user_id'] ?? null;
        return $id !== null ? (int)$id : null;
    }

    public function getSessionOrgId(): ?int
    {
        $id = $_SESSION[$this->sessionPrefix]['DASHBOARD']['organization_id'] ?? null;
        return $id !== null ? (int)$id : null;
    }

    public function getSessionRoleId(): ?int
    {
        $id = $_SESSION[$this->sessionPrefix]['DASHBOARD']['role_id'] ?? null;
        return $id !== null ? (int)$id : null;
    }

    public function isAuthenticated(): bool
    {
        return $this->getSessionUserId() !== null;
    }
}
