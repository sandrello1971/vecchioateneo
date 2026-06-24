<?php

namespace App\Http\Requests\Admin;

use App\Models\CourseConceptMap;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseConceptMapRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La rotta è già protetta dal middleware admin.auth: nessun check aggiuntivo qui.
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'visibility' => 'sometimes|in:' . CourseConceptMap::VISIBILITY_DRAFT . ',' . CourseConceptMap::VISIBILITY_PUBLISHED,
            'sort_order' => 'nullable|integer|min:0',
            'data' => 'sometimes|array',
            'data.nodes' => 'required_with:data|array',
            'data.nodes.*.id' => 'required|string|max:64',
            'data.nodes.*.label' => 'required|string|max:120',
            'data.nodes.*.description' => 'nullable|string|max:1000',
            'data.nodes.*.link_type' => 'nullable|in:module,material,url',
            'data.nodes.*.link_module_id' => 'nullable|uuid',
            'data.nodes.*.link_material_id' => 'nullable|uuid',
            'data.nodes.*.link_url' => 'nullable|url|max:2048',
            'data.nodes.*.x' => 'nullable|numeric',
            'data.nodes.*.y' => 'nullable|numeric',
            'data.edges' => 'required_with:data|array',
            'data.edges.*.id' => 'required|string|max:64',
            'data.edges.*.from' => 'required|string|max:64',
            'data.edges.*.to' => 'required|string|max:64',
            'data.edges.*.label' => 'required|string|max:60',
        ];
    }
}
