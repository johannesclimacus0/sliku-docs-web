<?php

declare(strict_types=1);

namespace App\Modules\Account\Controllers;

use App\Storage\Storage;
use League\Flysystem\UnableToReadFile;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Stream;

class AvatarMediaController
{
    public function __construct(private Storage $storage)
    {
    }

    public function show(string $filename, ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $stream = $this->storage->disk('public')->readStream('avatars/' . $filename);
        } catch (UnableToReadFile $error) {
            throw new HttpNotFoundException($request, previous: $error);
        }
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $mime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$extension];

        return $response->withBody(new Stream($stream))
            ->withHeader('Content-Type', $mime)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Cache-Control', 'public, max-age=86400');
    }
}
