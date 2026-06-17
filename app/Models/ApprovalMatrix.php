<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalMatrix extends Model
{
    use HasFactory;

    protected $fillable = [
        'submitter_level',
        'approver_1_level',
        'approver_2_level',
        'final_verifier_role',
    ];
}
