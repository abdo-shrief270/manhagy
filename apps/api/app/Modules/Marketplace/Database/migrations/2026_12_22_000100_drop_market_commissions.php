<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/** The market takes no commission (accounts are billed by quotation instead). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('market_commissions');
    }
};
