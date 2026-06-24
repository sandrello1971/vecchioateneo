<?php

namespace App\Http\Requests\Admin;

use App\Models\CourseConceptMap;
use Illuminate\Foundation\Http\FormRequest;

class StoreCourseConceptMapRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La rotta è già protetta dal middleware admin.auth: nessun check aggiuntivo qui.
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'visibility' => 'nullable|in:' . CourseConceptMap::VISIBILITY_DRAFT . ',' . CourseConceptMap::VISIBILITY_PUBLISHED,
            'sort_order' => 'nullable|integer|min:0',
            'module_id' => 'nullable|uuid',
        ];
    }
}
