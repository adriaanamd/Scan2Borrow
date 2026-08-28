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
            $token = htmlspecialchars($this->csrf->token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $injected = preg_replace(
                '/<meta\s+name="csrf"\s+content="[^"]*"\s*\/?\s*>/i',
                '<meta name="csrf" content="' . $token . '">',
                $html,
                1,
            );
            $html = is_string($injected) ? $injected : $html;
        }

        return new HtmlResponse(200, $html);
    }
}
