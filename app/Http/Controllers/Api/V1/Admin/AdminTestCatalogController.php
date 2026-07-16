<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TestCatalogRequest;
use App\Http\Resources\TestCatalogResource;
use App\Models\TestCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminTestCatalogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'food_category' => ['sometimes', 'string'],
            'lab_section' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = TestCatalog::query()->orderBy('food_category')->orderBy('test_name');

        foreach (['food_category', 'lab_section'] as $filter) {
            if (isset($validated[$filter])) {
                $query->where($filter, $validated[$filter]);
            }
        }

        return TestCatalogResource::collection(
            $query->paginate($validated['per_page'] ?? 20)->withQueryString()
        );
    }

    public function store(TestCatalogRequest $request): JsonResponse
    {
        $entry = TestCatalog::create($request->validated());

        return (new TestCatalogResource($entry))->response()->setStatusCode(201);
    }

    public function show(TestCatalog $testCatalog): TestCatalogResource
    {
        return new TestCatalogResource($testCatalog);
    }

    public function update(TestCatalogRequest $request, TestCatalog $testCatalog): TestCatalogResource
    {
        $testCatalog->update($request->validated());

        return new TestCatalogResource($testCatalog->refresh());
    }

    public function destroy(TestCatalog $testCatalog): JsonResponse
    {
        $testCatalog->delete();

        return response()->json([
            'data' => ['message' => 'Test catalog entry deleted.'],
            'meta' => [],
        ]);
    }
}
