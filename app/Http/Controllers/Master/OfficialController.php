<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Rules\UserRule;
use App\Services\UserRoles\OfficialUserService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OfficialController extends Controller
{
    public function __construct(
        protected OfficialUserService $officialUserService,
        protected UserService $userService,
        protected UserRule $userRule,
    ) {}

    public function index()
    {
        return Inertia::render('masters/users/official/index', [
            'dataUser' => $this->officialUserService->getForDataTable(),
            'dataRole' => $this->userService->getRoleForFilter(),
        ]);
    }

    public function create()
    {
        return Inertia::render('masters/users/create', [
            'dataRole' => $this->userService->getRoleForFilter(),
            'isOfficial' => true,
            'submitUrl' => '/master/user/official',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->userRule->rules('official'));

        $user = $this->officialUserService->store($validated);

        return redirect()->intended(route('master.user.official.index'))->with('success', 'Official created successfully.');
    }

    public function show(string $id)
    {
        return Inertia::render('masters/users/view', [
            'data' => $this->officialUserService->getForEditShow($id),
            'dataRole' => $this->userService->getRoleForFilter(),
            'isOfficial' => true,
        ]);
    }

    public function edit(string $id)
    {
        return Inertia::render('masters/users/edit', [
            'data' => $this->officialUserService->getForEditShow($id),
            'dataRole' => $this->userService->getRoleForFilter(),
            'isOfficial' => true,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate($this->userRule->rules('official', 'update', $id));

        $this->officialUserService->update($validated, $id);

        return redirect()->intended(route('master.user.official.index'))->with('success', 'Official updated successfully.');
    }

    public function destroy(Request $request, string $id)
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $userId) {
                $this->userService->delete($userId);
            }

            return redirect()->intended(route('master.user.official.index'))->with('success', 'Selected officials deleted successfully.');
        }

        $this->userService->delete($id);

        return redirect()->intended(route('master.user.official.index'))->with('success', 'Official deleted successfully.');
    }
}
