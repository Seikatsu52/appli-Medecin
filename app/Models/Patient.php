<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use HasFactory;

    protected $table = 'patients';

    protected $primaryKey = 'idPatient';

    protected $fillable = [
        'nomPatient',
        'prenomPatient',
        'ruePatient',
        'cpPatient',
        'villePatient',
        'telPatient',
        'loginPatient',
        'mdpPatient',
    ];

    protected $hidden = [
        'mdpPatient',
    ];

    public function rdvs(): HasMany
    {
        return $this->hasMany(Rdv::class, 'idPatient', 'idPatient');
    }

    public function authentifications(): HasMany
    {
        return $this->hasMany(Authentification::class, 'idPatient', 'idPatient');
    }
}
