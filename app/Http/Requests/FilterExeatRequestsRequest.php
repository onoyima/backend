<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilterExeatRequestsRequest extends FormRequest
{
    public function authorize()
    {
        // Authorization handled in controller
        return true;
    }

    public function rules()
    {
        return [
            'status' => 'nullable|array',
            'status.*' => 'string|in:pending,approved,rejected,cancelled,completed',
            'department' => 'nullable|string|max:255',
            'hostel' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'student_name' => 'nullable|string|max:255',
            'student_id' => 'nullable|string|max:50',
            'approval_stage' => 'nullable|string|in:housemaster,dean,cmd,security,hostel_admin',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'sort_by' => 'nullable|string|in:created_at,updated_at,departure_date,return_date,student_name',
            'sort_order' => 'nullable|string|in:asc,desc',
        ];
    }

    public function messages()
    {
        return [
            'status.*.in' => 'Invalid status value. Must be one of: pending, approved, rejected, cancelled, completed.',
            'date_to.after_or_equal' => 'End date must be after or equal to start date.',
            'approval_stage.in' => 'Invalid approval stage. Must be one of: housemaster, dean, cmd, security, hostel_admin.',
            'priority.in' => 'Invalid priority level. Must be one of: low, medium, high, urgent.',
            'per_page.max' => 'Cannot display more than 100 items per page.',
            'sort_by.in' => 'Invalid sort field.',
            'sort_order.in' => 'Sort order must be either asc or desc.',
        ];
    }
}
