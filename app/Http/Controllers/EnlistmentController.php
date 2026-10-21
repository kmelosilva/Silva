<?php

namespace App\Http\Controllers;

use App\Http\Requests\CodenameRequest;
use App\Http\Requests\PrioritiesRequest;
use App\Http\Resources\EnlistmentResource;
use App\Models\Agent;
use App\Services\Enlistment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnlistmentController extends Controller
{
    public function __construct(private readonly Enlistment $enlistment) {}

    /**
     * O passo do alistamento e os textos; retomar é só pedir de novo.
     */
    public function show(Request $request): JsonResponse
    {
        return $this->state($request->user()->ensureAgent());
    }

    public function oath(Request $request): JsonResponse
    {
        return $this->state($this->enlistment->acceptOath($request->user()));
    }

    public function codename(CodenameRequest $request): JsonResponse
    {
        return $this->state($this->enlistment->chooseCodename($request->user(), $request->validated('name')));
    }

    public function complete(PrioritiesRequest $request): JsonResponse
    {
        return $this->state($this->enlistment->complete($request->user(), $request->priorities()));
    }

    /**
     * 200 sempre: o Resource responderia 201 quando o agente acabou de ser criado.
     */
    private function state(Agent $agent): JsonResponse
    {
        return (new EnlistmentResource($agent))->response()->setStatusCode(200);
    }
}
