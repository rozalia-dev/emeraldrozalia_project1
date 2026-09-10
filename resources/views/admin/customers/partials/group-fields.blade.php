<label>Group Name<input name="name" required value="{{ old('name',$editing?->name) }}"></label>
<label>Group Type<select name="type">@foreach(['retail'=>'Retail','corporate'=>'Corporate','franchise'=>'Franchise','franchise_retail'=>'Franchise Retail','bulk'=>'Bulk','buyer'=>'Buyer','vip'=>'VIP / Loyalty','system'=>'Other / System'] as $key=>$label)<option value="{{ $key }}" @selected(old('type',$editing?->type??'retail')===$key)>{{ $label }}</option>@endforeach</select></label>
<label class="is-wide">Description<textarea name="description">{{ old('description',$editing?->description) }}</textarea></label>
<label>Pricing Rule<input name="pricing_rule" placeholder="e.g. Franchise Pricing" value="{{ old('pricing_rule',$editing?->pricing_rule) }}"></label>
<label>Discount / Benefit<input name="discount_note" placeholder="e.g. Partner Discounts" value="{{ old('discount_note',$editing?->discount_note) }}"></label>
<label>Country<input name="country" maxlength="2" placeholder="IE" value="{{ old('country',$editing?->country) }}"></label>
<label>Customers<select name="customer_ids[]" multiple size="7">@foreach($availableCustomers as $customer)<option value="{{ $customer->id }}" @selected(in_array($customer->id,old('customer_ids',$editing?->customers?->pluck('id')->all()??[])))>{{ $customer->name }} — {{ $customer->email }}</option>@endforeach</select></label>
<label class="crm-check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$editing?->is_active??true))> Active Group</label>
<label class="crm-check"><input type="hidden" name="is_vip" value="0"><input type="checkbox" name="is_vip" value="1" @checked(old('is_vip',$editing?->is_vip??false))> VIP / Exclusive Group</label>
