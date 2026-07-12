<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlanRequest;
use App\Http\Requests\UpdatePlanRequest;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Request;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Plan::class);

        $plans = Plan::query()
            ->search(Request::query('search'))
            ->orderBy('plan_name')
            ->paginate(15)
            ->withQueryString();

        return view('plans.index', compact('plans'));
    }

    public function store(StorePlanRequest $request): RedirectResponse
    {
        Plan::create($request->validated());

        return redirect()->route('plans.index')->with('status', 'Plan created successfully.');
    }

    public function update(UpdatePlanRequest $request, Plan $plan): RedirectResponse
    {
        $plan->update($request->validated());

        return redirect()->route('plans.index')->with('status', 'Plan updated successfully.');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        $this->authorize('delete', $plan);

        $plan->delete();

        return redirect()->route('plans.index')->with('status', "\"{$plan->plan_name}\" deleted.");
    }
}
