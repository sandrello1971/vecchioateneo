<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'instructor_id' => 'required|uuid|exists:students,id',
            'course_id'     => 'required|uuid|exists:courses,id',
            'subject'       => 'required|string|min:3|max:200',
            'body'          => 'required|string|min:1|max:5000',
        ];
    }
}
