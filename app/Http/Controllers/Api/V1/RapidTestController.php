<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RapidTest\ListRapidTestRequest;
use App\Http\Requests\RapidTest\StoreRapidTestRequest;
use App\Http\Resources\RapidTestResource;
use App\Models\RapidTest;
use App\Services\PremisesResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RapidTestController extends Controller
{
    public function __construct(private readonly PremisesResolver $premisesResolver)
    {
    }

    /**
     * List rapid tests (paginated).
     *
     * TODO: scope to the FSO's own district once district is modelled on users/
     * premises. Not modelled in Phase 1, so no district filter is applied yet.
     */
    public function index(ListRapidTestRequest $request): AnonymousResourceCollection
    {
        $query = RapidTest::query()->with('premises')->latest('tested_at');

        if ($request->filled('premises_license')) {
            $license = $request->string('premises_license');
            $query->whereHas('premises', fn ($q) => $q->where('license_no', $license));
        }

        if ($request->filled('from')) {
            $query->where('tested_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->where('tested_at', '<=', $request->date('to'));
        }

        return RapidTestResource::collection(
            $query->paginate($request->integer('per_page', 20))->withQueryString()
        );
    }

    /**
     * Record a rapid screening test. Resolves the premises by license (auto-creating
     * a MANUAL fallback if unknown) and stores any photo on the private disk.
     */
    public function store(StoreRapidTestRequest $request): JsonResponse
    {
        $premises = $this->premisesResolver->resolveByLicense(
            $request->string('premises_license'),
            [
                'name' => $request->input('premises_name'),
                'address' => $request->input('premises_address'),
                'city' => $request->input('premises_city'),
            ],
        );

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('rapid-tests', 'local');
        }

        $rapidTest = RapidTest::create([
            'premises_id' => $premises->id,
            'fso_id' => $request->user()->id,
            'device' => $request->string('device'),
            'reading' => $request->string('reading'),
            'passed' => $request->boolean('passed'),
            'photo_path' => $photoPath,
            'tested_at' => $request->date('tested_at'),
        ]);

        $rapidTest->load('premises');

        return (new RapidTestResource($rapidTest))
            ->response()
            ->setStatusCode(201);
    }
}
