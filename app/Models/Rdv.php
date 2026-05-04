<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rdv extends Model
{
    use HasFactory;

    protected $table = 'rdv';

    protected $primaryKey = 'idRdv';

    protected $fillable = [
        'dateHeureRdv',
        'idPatient',
        'nomMedecin',
        'prenomMedecin',
        'idMedecin',
    ];

    protected function casts(): array
    {
        return [
            'dateHeureRdv' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'idPatient', 'idPatient');
    }
}
