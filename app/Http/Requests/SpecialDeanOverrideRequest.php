<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SpecialDeanOverrideRequest extends FormRequest
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
            'override_reason' => 'required|string|max:1000',
            'emergency_contact' => 'nullable|string|max:255',
            'special_instructions' => 'nullable|string|max:1000',
            'bypass_security_check' => 'boolean',
            'bypass_hostel_signout' => 'boolean',
        ];
    }

    public function messages()
    {
        return [
            'exeat_request_ids.required' => 'At least one exeat request must be selected for override.',
            'exeat_request_ids.array' => 'Exeat request IDs must be provided as an array.',
            'exeat_request_ids.min' => 'At least one exeat request must be selected for override.',
            'exeat_request_ids.*.exists' => 'One or more selected exeat requests do not exist.',
            'override_reason.required' => 'Override reason is required for special dean approval.',
            'override_reason.max' => 'Override reason cannot exceed 1000 characters.',
            'emergency_contact.max' => 'Emergency contact cannot exceed 255 characters.',
            'special_instructions.max' => 'Special instructions cannot exceed 1000 characters.',
        ];
    }
}
