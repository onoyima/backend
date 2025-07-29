<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StaffExeatApprovalRequest extends FormRequest
{
    public function authorize()
    {
        // Authorization handled in controller
        return true;
    }

    public function rules()
    {
        return [
            'comment' => 'nullable|string',
        ];
    }
}