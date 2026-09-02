<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\AdminRecord;
use App\Services\AuditTrail;
use Illuminate\Http\Request;
class ResourceController extends Controller {
    public array $modules=['website-products','online-sales','customers','franchise-management','communication-center','reports','users-roles','integrations','settings','audit-logs','automation','backup-recovery','system-maintenance','returns-refunds','media-manager','collections','discounts-coupons','reviews-testimonials','shipping-delivery'];
    private function valid(string $module):void { abort_unless(in_array($module,$this->modules,true),404); }
    private function data(Request $r):array { return $r->validate(['title'=>'required|max:180','reference'=>'nullable|max:100','status'=>'required|max:50','amount'=>'nullable|numeric','record_date'=>'nullable|date','notes'=>'nullable|max:3000']); }
    public function index(string $module){$this->valid($module);$records=AdminRecord::where('module',$module)->latest()->paginate(25);return view('admin.resources.index',compact('module','records'));}
    public function store(Request $r,string $module){$this->valid($module);$d=$this->data($r);$record=AdminRecord::create(['module'=>$module,'title'=>$d['title'],'reference'=>$d['reference']??null,'status'=>$d['status'],'amount'=>$d['amount']??null,'record_date'=>$d['record_date']??null,'user_id'=>auth()->id(),'data'=>['notes'=>$d['notes']??null]]);AuditTrail::record($module.'.created',$record,null,$record->toArray());return back()->with('success','Record created.');}
    public function update(Request $r,string $module,AdminRecord $record){$this->valid($module);abort_unless($record->module===$module,404);$before=$record->toArray();$d=$this->data($r);$record->update(['title'=>$d['title'],'reference'=>$d['reference']??null,'status'=>$d['status'],'amount'=>$d['amount']??null,'record_date'=>$d['record_date']??null,'data'=>['notes'=>$d['notes']??null]]);AuditTrail::record($module.'.updated',$record,$before,$record->fresh()->toArray());return back()->with('success','Record updated.');}
    public function destroy(string $module,AdminRecord $record){$this->valid($module);abort_unless($record->module===$module,404);$before=$record->toArray();AuditTrail::record($module.'.deleted',$record,$before,null);$record->delete();return back()->with('success','Record deleted.');}
}
