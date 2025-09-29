<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use App\Models\StudentExeatDebt;
use App\Models\Student;
use Exception;

class PaystackService
{
    protected $secretKey;
    protected $publicKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->secretKey = Config::get('services.paystack.secret_key');
        $this->publicKey = Config::get('services.paystack.public_key');
        $this->baseUrl = Config::get('services.paystack.payment_url');
    }

    /**
     * Initialize a payment transaction
     *
     * @param StudentExeatDebt $debt
     * @param Student $student
     * @return array
     */
    public function initializeTransaction(StudentExeatDebt $debt, Student $student)
    {
        try {
            // Use total amount with charge if available, otherwise use original amount
            $chargeAmount = $debt->total_amount_with_charge ?? $debt->amount;
            $amount = $chargeAmount * 100; // Convert to kobo (Paystack uses the smallest currency unit)
            $reference = 'EXEAT-DEBT-' . $debt->id . '-' . time();
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($this->baseUrl . '/transaction/initialize', [
                'amount' => $amount,
                'email' => $student->email,
                'reference' => $reference,
                'callback_url' => route('student.debts.verify-payment', ['debt' => $debt->id]),
                'metadata' => [
                    'debt_id' => $debt->id,
                    'student_id' => $student->id,
                    'exeat_request_id' => $debt->exeat_request_id,
                    'original_amount' => $debt->amount,
                    'processing_charge' => $debt->processing_charge ?? 0,
                    'total_amount' => $debt->total_amount_with_charge ?? $debt->amount,
                    'custom_fields' => [
                        [
                            'display_name' => 'Debt Type',
                            'variable_name' => 'debt_type',
                            'value' => 'Exeat Overdue Fee'
                        ],
                        [
                            'display_name' => 'Student Name',
                            'variable_name' => 'student_name',
                            'value' => $student->full_name
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Update the debt record with the payment reference
                $debt->payment_reference = $reference;
                $debt->save();
                
                return [
                    'success' => true,
                    'data' => $data['data'],
                    'message' => 'Payment initialized successfully'
                ];
            }
            
            Log::error('Paystack initialization failed', [
                'debt_id' => $debt->id,
                'student_id' => $student->id,
                'response' => $response->json()
            ]);
            
            return [
                'success' => false,
                'message' => 'Unable to initialize payment. Please try again.'
            ];
        } catch (Exception $e) {
            Log::error('Paystack transaction initialization error', [
                'debt_id' => $debt->id,
                'student_id' => $student->id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while processing your payment. Please try again.'
            ];
        }
    }

    /**
     * Verify a payment transaction
     *
     * @param string $reference
     * @return array
     */
    public function verifyTransaction($reference)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get($this->baseUrl . '/transaction/verify/' . $reference);

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['data']['status'] === 'success') {
                    return [
                        'success' => true,
                        'data' => $data['data'],
                        'message' => 'Payment verified successfully'
                    ];
                }
                
                return [
                    'success' => false,
                    'data' => $data['data'],
                    'message' => 'Payment verification failed. Status: ' . $data['data']['status']
                ];
            }
            
            Log::error('Paystack verification failed', [
                'reference' => $reference,
                'response' => $response->json()
            ]);
            
            return [
                'success' => false,
                'message' => 'Unable to verify payment. Please contact support.'
            ];
        } catch (Exception $e) {
            Log::error('Paystack transaction verification error', [
                'reference' => $reference,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while verifying your payment. Please contact support.'
            ];
        }
    }
}