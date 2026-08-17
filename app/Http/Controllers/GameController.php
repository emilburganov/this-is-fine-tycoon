<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Services\GameEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class GameController extends Controller
{
    public function __construct(private GameEngine $engine) {}

    public function show(Request $request): JsonResponse
    {
        $game = $this->resolveGame($request, create: true);

        return $this->withCookie(response()->json($this->engine->snapshot($game)), $game);
    }

    public function sip(Request $request): JsonResponse
    {
        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:40'],
            'combo' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $game = $this->resolveGame($request, create: true);
        $result = $this->engine->sip($game, (int) $data['count'], (int) ($data['combo'] ?? 1));

        return $this->withCookie(response()->json($result), $game);
    }

    public function upgrade(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'string', 'max:40'],
        ]);

        $game = $this->resolveGame($request, create: true);

        return $this->withCookie(
            response()->json($this->engine->buyUpgrade($game, $data['id'])),
            $game
        );
    }

    public function action(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'in:go_live,relight,dismiss,copium'],
        ]);

        $game = $this->resolveGame($request, create: true);

        $state = match ($data['name']) {
            'go_live' => $this->engine->goLive($game),
            'relight' => $this->engine->relight($game),
            'copium' => $this->engine->dismissEvent($game, 'copium'),
            default => $this->engine->dismissEvent($game),
        };

        return $this->withCookie(response()->json($state), $game);
    }

    public function monetize(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:40'],
        ]);

        $game = $this->resolveGame($request, create: true);

        return $this->withCookie(
            response()->json($this->engine->buyMonetization($game, $data['sku'])),
            $game
        );
    }

    public function reset(Request $request): JsonResponse
    {
        $old = $this->findGame($request);
        $old?->delete();

        $game = $this->engine->createSave();

        return $this->withCookie(response()->json($this->engine->snapshot($game)), $game);
    }

    private function resolveGame(Request $request, bool $create = false): Game
    {
        $game = $this->findGame($request);

        if ($game) {
            return $game;
        }

        if (! $create) {
            abort(404, 'Сохранение не найдено');
        }

        return $this->engine->createSave();
    }

    private function findGame(Request $request): ?Game
    {
        $uuid = $request->header('X-Fine-Save')
            ?: $request->cookie(config('game.cookie'));

        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        return Game::where('uuid', $uuid)->first();
    }

    private function withCookie(JsonResponse $response, Game $game): JsonResponse
    {
        $response->headers->setCookie(
            Cookie::make(config('game.cookie'), $game->uuid, 60 * 24 * 400, '/', null, false, false, false, 'lax')
        );

        return $response;
    }
}
