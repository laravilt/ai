<?php

declare(strict_types=1);

namespace Laravilt\AI\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravilt\AI\GlobalSearch;

class GlobalSearchController extends Controller
{
    public function __construct(
        protected GlobalSearch $globalSearch
    ) {}

    public function search(Request $request): JsonResponse
    {
        $query = $request->input('query', '');
        $useAI = $request->boolean('useAI', true);

        $results = $this->globalSearch
            ->useAI($useAI)
            ->search($query);

        return response()->json([
            'results' => $results,
            'query' => $query,
        ]);
    }

    public function resources(): JsonResponse
    {
        return response()->json([
            'resources' => collect($this->globalSearch->getResources())->map(function ($resource) {
                return [
                    'resource' => $resource['resource'],
                    'label' => $resource['label'],
                    'icon' => $resource['icon'],
                ];
            }),
        ]);
    }
}
