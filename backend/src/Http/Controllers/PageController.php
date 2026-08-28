<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\CsrfService;
use App\Http\Exceptions\HttpException;
use App\Http\Requests\ServerRequest;
use App\Http\Responses\HtmlResponse;
use App\Http\Responses\RedirectResponse;
use App\Http\Responses\ResponseInterface;
use App\Http\Routing\PageAccessAuthorizerInterface;
use App\Http\Routing\PageRoute;

final readonly class PageController
{
    public function __construct(
        private PageAccessAuthorizerInterface $authorizer,
        private ?CsrfService $csrf = null,
    ) {
    }

    public function __invoke(ServerRequest $request, PageRoute $route): ResponseInterface
    {
        if (!$this->authorizer->allows($request, $route)) {
            return new RedirectResponse(302, $this->authorizer->denialLocation($request, $route));
        }

        if (!is_file($route->templatePath()) || !is_readable($route->templatePath())) {
            throw new HttpException(404, ['Page not found.']);
        }

        $html = file_get_contents($route->templatePath());
        if ($html === false) {
            throw new HttpException(404, ['Page not found.']);
        }

        if ($this->csrf !== null) {
            $html = str_replace(
                '<meta name="csrf" content="">',
                '<meta name="csrf" content="' . htmlspecialchars($this->csrf->token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">',
                $html,
            );
        }

        $html = str_replace(
            '</head>',
            '<script src="/scan2borrow/frontend/assets/js/core/encoding.js" defer></script></head>',
            $html,
        );

        return new HtmlResponse(200, $html);
    }
}
