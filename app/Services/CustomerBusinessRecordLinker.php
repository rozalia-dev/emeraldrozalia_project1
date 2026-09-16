<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CustomerBusinessRecordLinker
{
    public function claimFor(User $user): int
    {
        if (! $user->hasVerifiedEmail()) {
            return 0;
        }

        $email = mb_strtolower(trim((string) $user->email));
        if ($email === '') {
            return 0;
        }

        return DB::transaction(function () use ($user, $email): int {
            $claimed = 0;
            $inquiries = Inquiry::withoutGlobalScopes()
                ->whereNull('customer_id')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->lockForUpdate()
                ->get();

            foreach ($inquiries as $inquiry) {
                $before = $inquiry->toArray();
                $inquiry->update(['customer_id' => $user->id]);

                DB::table('conversations')
                    ->where('inquiry_id', $inquiry->id)
                    ->whereNull('customer_id')
                    ->whereRaw('LOWER(contact) = ?', [$email])
                    ->update(['customer_id' => $user->id]);

                DB::table('franchise_applications')
                    ->where('inquiry_id', $inquiry->id)
                    ->whereNull('customer_id')
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->update(['customer_id' => $user->id]);

                $quoteIds = DB::table('sales_quotes')
                    ->where('inquiry_id', $inquiry->id)
                    ->whereNull('customer_id')
                    ->pluck('id');

                if ($quoteIds->isNotEmpty()) {
                    DB::table('sales_quotes')
                        ->whereIn('id', $quoteIds)
                        ->update(['customer_id' => $user->id]);

                    DB::table('orders')
                        ->whereIn('quote_id', $quoteIds)
                        ->whereNull('user_id')
                        ->update(['user_id' => $user->id]);
                }

                AuditTrail::record('customer.business_record_claimed', $inquiry, $before, [
                    'customer_id' => $user->id,
                    'email_verified' => true,
                    'claim_basis' => 'verified_email_match',
                ]);
                $claimed++;
            }

            // Preserve access to older franchise applications created before the
            // inquiry relationship existed. Verification of the same mailbox is
            // the ownership proof; a logged-in session alone is never sufficient.
            $claimed += DB::table('franchise_applications')
                ->whereNull('customer_id')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->update(['customer_id' => $user->id]);

            return $claimed;
        });
    }
}
