<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkExeatOperationRequest extends FormRequest
{
    public function authorize()
    {
        // Authorization handled in controller
        return true;
    }

    public function rules()
    {
        return [
            'exeat_request_ids' => 'required|array|min:1',
            'exeat_request_ids.*' => 'required|integer|exists:exeat_requests,id',
            'comment' => 'nullable|string|max:1000',
            'reason' => 'nullable|string|max:500',
        ];
    }

    public function messages()
    {
        return [
            'exeat_request_ids.required' => 'At least one exeat request must be selected.',
            'exeat_request_ids.array' => 'Exeat request IDs must be provided as an array.',
            'exeat_request_ids.min' => 'At least one exeat request must be selected.',
            'exeat_request_ids.*.exists' => 'One or more selected exeat requests do not exist.',
            'comment.max' => 'Comment cannot exceed 1000 characters.',
            'reason.max' => 'Reason cannot exceed 500 characters.',
        ];
    }
}
