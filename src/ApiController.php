<?php

declare(strict_types=1);

final class ApiController
{
    public function __construct(private readonly StoryRepository $stories)
    {
    }

    public function handle(string $method, string $path): void
    {
        try {
            $this->route($method, $path);
        } catch (ValidationException $exception) {
            $this->json(['error' => $exception->getMessage()], $exception->httpStatus());
        } catch (Throwable $exception) {
            $this->json(['error' => 'Serveri viga. Proovi uuesti.'], 500);
        }
    }

    private function route(string $method, string $path): void
    {
        if ($path === '/api/stories' && $method === 'GET') {
            $this->json($this->stories->all());
            return;
        }

        if ($path === '/api/stories' && $method === 'POST') {
            $this->json($this->stories->create($this->body()), 201);
            return;
        }

        if ($path === '/api/stories/reorder' && $method === 'PATCH') {
            $body = $this->body();
            $items = $body['stories'] ?? $body;
            if (!is_array($items)) {
                throw new ValidationException('Järjestus peab olema nimekiri.');
            }
            $this->json($this->stories->reorder($items));
            return;
        }

        if (preg_match('#^/api/stories/(\d+)$#', $path, $matches)) {
            $id = (int) $matches[1];
            match ($method) {
                'GET' => $this->json($this->stories->find($id)),
                'PUT' => $this->json($this->stories->update($id, $this->body())),
                'DELETE' => $this->deleteStory($id),
                default => $this->json(['error' => 'Meetod ei ole lubatud.'], 405),
            };
            return;
        }

        if (preg_match('#^/api/stories/(\d+)/status$#', $path, $matches) && $method === 'PATCH') {
            $body = $this->body();
            $this->json($this->stories->updateStatus((int) $matches[1], (string) ($body['status'] ?? '')));
            return;
        }

        if (preg_match('#^/api/stories/(\d+)/comments$#', $path, $matches) && $method === 'POST') {
            $body = $this->body();
            $this->json($this->stories->addComment((int) $matches[1], (string) ($body['text'] ?? '')), 201);
            return;
        }

        if (preg_match('#^/api/stories/(\d+)/comments/(\d+)$#', $path, $matches) && $method === 'DELETE') {
            $this->json($this->stories->deleteComment((int) $matches[1], (int) $matches[2]));
            return;
        }

        if (str_starts_with($path, '/api/')) {
            $this->json(['error' => 'Endpointi ei leitud.'], 404);
            return;
        }

        $this->json(['error' => 'Meetod ei ole lubatud.'], 405);
    }

    private function deleteStory(int $id): void
    {
        $this->stories->delete($id);
        http_response_code(204);
    }

    /** @return array<string, mixed> */
    private function body(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            throw new ValidationException('Päringu keha peab olema korrektne JSON.');
        }

        return $data;
    }

    private function json(mixed $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}

