@php $profile=$editing?->customerProfile; @endphp
<label>Name<input name="name" required value="{{ old('name',$editing?->name) }}"></label>
<label>Email<input type="email" name="email" required value="{{ old('email',$editing?->email) }}"></label>
<label>Phone<input name="phone" value="{{ old('phone',$editing?->phone) }}"></label>
<label>Account Status<select name="account_status">@foreach(['active','inactive','blocked','restricted'] as $s)<option value="{{ $s }}" @selected(old('account_status',$profile?->account_status??'active')===$s)>{{ str($s)->headline() }}</option>@endforeach</select></label>
<label>Primary Customer Group<select name="group_id"><option value="">Unassigned</option>@foreach($groups as $group)<option value="{{ $group->id }}" @selected((int)old('group_id',$profile?->primary_group_id)===$group->id)>{{ $group->name }}</option>@endforeach</select></label>
<label>Country<input name="country" maxlength="2" placeholder="IE" value="{{ old('country',$profile?->country) }}"></label>
<label>Preferred Language<input name="preferred_language" maxlength="10" value="{{ old('preferred_language',$profile?->preferred_language??'en') }}"></label>
<label>Segments<select name="segment_ids[]" multiple size="4">@foreach($segments as $segment)<option value="{{ $segment->id }}" @selected(in_array($segment->id,old('segment_ids',$editing?->customerSegments?->pluck('id')->all()??[])))>{{ $segment->name }}</option>@endforeach</select></label>
<label class="crm-check"><input type="hidden" name="is_vip" value="0"><input type="checkbox" name="is_vip" value="1" @checked(old('is_vip',$profile?->is_vip??false))> VIP / Loyalty Customer</label>
<label class="crm-check"><input type="hidden" name="marketing_consent" value="0"><input type="checkbox" name="marketing_consent" value="1" @checked(old('marketing_consent',$profile?->marketing_consent??false))> Marketing Consent</label>
<label class="is-wide">Notes<textarea name="notes" placeholder="Internal customer notes">{{ old('notes',$profile?->notes) }}</textarea></label>
