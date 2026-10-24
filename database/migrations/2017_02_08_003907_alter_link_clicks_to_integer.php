<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class AlterLinkClicksToInteger extends Migration
{
    /**
     * Run the migrations.
     * Changes the "clicks" field in the link table to
     * an integer field rather than a string field.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('links', function (Blueprint $table)
        {
            // Without doctrine/dbal, change() replaces the whole column definition,
            // so restate the default that Polr 2.x kept.
            $table->integer('clicks')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('links', function (Blueprint $table)
        {
            $table->string('clicks')->default(0)->change();
        });
    }
}
