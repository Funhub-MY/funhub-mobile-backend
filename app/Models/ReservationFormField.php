<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ReservationFormField extends BaseModel implements Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable;

    protected $table = 'reservation_form_fields';

    protected $guarded = ['id'];

    protected $casts = [
        'form_fields' => 'array',
    ];

    /**
     * Get formatted form fields as JSON string for display
     */
    public function getFormFieldsJsonAttribute(): string
    {
        $formFields = $this->form_fields;
        if (is_array($formFields)) {
            return json_encode($formFields, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        if (is_string($formFields)) {
            return $formFields;
        }
        return '[]';
    }

    /**
     * Transform audit old/new values for form_fields to be display-friendly
     */
    public function transformAudit(array $data): array
    {
        if (isset($data['old_values']['form_fields']) && is_array($data['old_values']['form_fields'])) {
            $data['old_values']['form_fields'] = json_encode($data['old_values']['form_fields'], JSON_PRETTY_PRINT);
        }
        if (isset($data['new_values']['form_fields']) && is_array($data['new_values']['form_fields'])) {
            $data['new_values']['form_fields'] = json_encode($data['new_values']['form_fields'], JSON_PRETTY_PRINT);
        }
        return $data;
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }
}

