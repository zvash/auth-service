<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index()->nullable();
            $table->string('phone')->unique();
            $table->string('email')->unique()->nullable();
            $table->string('country')->index();
            $table->string('currency')->index();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->string('password');
            $table->string('referral_code')->nullable()->unique();
            $table->string('reset_password_token')->nullable();
            $table->timestamp('last_logged_in')->nullable();
            $table->string('image')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
