<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TontineRegulation extends Model
{
    protected $fillable = [
        'tontine_id', 'regulation_template_id', 'blocks_override',
        'generated_html', 'docuseal_template_id', 'status',
        'generated_at', 'sent_for_signature_at',
    ];

    protected $casts = [
        'blocks_override'        => 'array',
        'generated_at'           => 'datetime',
        'sent_for_signature_at'  => 'datetime',
    ];

    public function tontine()
    {
        return $this->belongsTo(Tontine::class);
    }

    public function template()
    {
        return $this->belongsTo(RegulationTemplate::class, 'regulation_template_id');
    }
}
