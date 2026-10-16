<?php

use App\Modules\Curriculum\Support\EgyptCurriculum;
use Illuminate\Database\Migrations\Migration;

/** The skills branch (field → skill) for trainers' courses. */
return new class extends Migration
{
    public function up(): void
    {
        EgyptCurriculum::seedSkills();
    }
};
