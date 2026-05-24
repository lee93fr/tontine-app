<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Template global du règlement (versionné par locale).
 * Le contenu est une liste ordonnée de blocs ; voir RegulationService pour la structure.
 *
 * Structure d'un bloc :
 * [
 *   'key'        => 'section_1',          // identifiant stable
 *   'title'      => '1. Objet du document',
 *   'type'       => 'text|table|signature_block|annexe_participants|annexe_rounds',
 *   'order'      => 100,
 *   'condition'  => null|'has_bidding'|'has_binome'|'has_double'|'has_penalties',
 *   'content'    => '<p>HTML avec variables {tontine_name} ...</p>',
 * ]
 */
class RegulationTemplate extends Model
{
    protected $fillable = ['name', 'locale', 'version', 'blocks', 'is_active'];

    protected $casts = [
        'blocks'    => 'array',
        'is_active' => 'bool',
    ];

    public static function active(string $locale = 'fr'): ?self
    {
        return static::where('locale', $locale)->where('is_active', true)->first();
    }
}
