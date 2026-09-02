<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Services\ExternalServiceGate;
class IntegrationStatusController extends Controller { public function __invoke(ExternalServiceGate $gate){return response()->json(['data'=>$gate->all(),'activation_sequence'=>['Deploy core website','Run migrations and tests','Verify SSL, queue, cron and backup restore','Put core website live','Enter one provider credential set','Verify callback and webhook signature','Enable that provider only']]);} }
