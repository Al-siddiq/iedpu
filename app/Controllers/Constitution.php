<?php
declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use RuntimeException;

/**
 * Read-only source-derived constitution reader.
 * Public preview until Shield member access is ready; no database mutations.
 */
final class Constitution extends BaseController
{
    private function document(): array
    {
        $path = APPPATH . 'Content/Constitution/2015/constitution.json';
        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('Constitution source unavailable.');
        }
        $document = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!isset($document['parts']) || !is_array($document['parts'])) {
            throw new RuntimeException('Invalid constitution source.');
        }
        return $document;
    }

    public function index(): string
    {
        helper('url');
        $document = $this->document();
        $query = trim((string) $this->request->getGet('q'));
        if (mb_strlen($query) > 80) {
            $query = mb_substr($query, 0, 80);
        }
        $matches = [];
        if ($query !== '') {
            foreach ($document['parts'] as $part) {
                if (mb_stripos($part['text'], $query) !== false || mb_stripos($part['title'], $query) !== false) {
                    $matches[] = $part;
                }
            }
        }
        return $this->render($document, null, $query, $matches);
    }

    public function show(string $slug): string
    {
        helper('url');
        $document = $this->document();
        $part = null;
        foreach ($document['parts'] as $candidate) {
            if ($candidate['key'] === $slug) {
                $part = $candidate;
                break;
            }
        }
        if ($part === null) {
            throw PageNotFoundException::forPageNotFound('Constitution section not found.');
        }
        return $this->render($document, $part, '', []);
    }

    private function render(array $document, ?array $part, string $query, array $matches): string
    {
        $this->response->setHeader('Cache-Control', 'no-store, private');
        $this->response->setHeader('X-Content-Type-Options', 'nosniff');
        return view('constitution_reader', [
            'document' => $document,
            'part' => $part,
            'query' => $query,
            'matches' => $matches,
        ]);
    }
}
