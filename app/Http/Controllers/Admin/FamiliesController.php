<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class FamiliesController extends Controller
{
    /**
     * Display a listing of template families.
     */
    public function index(): Response
    {
        return Inertia::render('Admin/pages/Families/Index');
    }

    /**
     * Show the form for creating a new template family.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/pages/Families/Create');
    }

    /**
     * Display the specified template family.
     */
    public function show(string $id): Response
    {
        return Inertia::render('Admin/pages/Families/Show', [
            'familyId' => $id,
        ]);
    }

    /**
     * Show the form for editing the specified template family.
     */
    public function edit(string $id): Response
    {
        return Inertia::render('Admin/pages/Families/Edit', [
            'familyId' => $id,
        ]);
    }
}
