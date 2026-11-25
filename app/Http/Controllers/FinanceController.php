<?php

namespace App\Http\Controllers;

use App\Models\{FinancialTransaction, ExpenseRequest, Division};
use App\Support\{Access, Workspace};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    public function index()
    {
        Access::ensure('finance-view');
        $period=Workspace::period();
        $base=FinancialTransaction::where('event_period_id',$period->id);
        $income=(clone $base)->where('transaction_type','income')->sum('amount');
        $expense=(clone $base)->where('transaction_type','expense')->sum('amount');
        $transactions=(clone $base)->with('division')->latest('transaction_date')->paginate(15);
        $requests=ExpenseRequest::where('event_period_id',$period->id)->latest()->take(25)->get();
        $divisions=Division::where('event_period_id',$period->id)->get();
        return view('panitia.finance',compact('period','income','expense','transactions','requests','divisions'));
    }

    public function store(Request $request)
    {
        Access::ensure('finance-manage',write:true);
        $period=Workspace::period();
        $data=$request->validate([
            'transaction_type'=>['required',Rule::in(['income','expense'])],
            'title'=>['required','string','max:200'],
            'amount'=>['required','numeric','gt:0','max:999999999999'],
            'transaction_date'=>['required','date'],
            'division_id'=>['nullable',Rule::exists('divisions','id')->where('event_period_id',$period->id)],
            'proof'=>['nullable','file','mimes:pdf,jpg,jpeg,png','max:5120'],
        ]);
        $file=$request->file('proof');
        $path=$file?->store('private/finance/'.$period->id, 'local');
        if ($file && !$path) abort(500, 'Bukti transaksi gagal disimpan.');
        unset($data['proof']);
        try {
            $transaction = DB::transaction(function () use ($data, $period, $path, $file): FinancialTransaction {
                $transaction = FinancialTransaction::create([...$data,'event_period_id'=>$period->id,'created_by'=>auth()->id(),'proof_file'=>$path,'proof_name'=>$file?->getClientOriginalName()]);
                Workspace::log('finance.created','Transaksi keuangan dicatat: '.$transaction->title,$transaction->id,'financial_transaction');
                return $transaction;
            });
        } catch (\Throwable $error) {
            if ($path) Storage::disk('local')->delete($path);
            throw $error;
        }
        return back()->with('success','Transaksi tersimpan. Koreksi transaksi harus melalui prosedur audit, bukan hapus langsung.');
    }

    public function requestExpense(Request $request)
    {
        Access::ensure('expenses-request',write:true);
        $period=Workspace::period();
        $data=$request->validate([
            'title'=>['required','string','max:200'],
            'description'=>['nullable','string','max:5000'],
            'amount_requested'=>['required','numeric','gt:0','max:999999999999'],
            'division_id'=>['required',Rule::exists('divisions','id')->where('event_period_id',$period->id)],
        ]);
        abort_unless(Access::can(auth()->user(),'expenses-request-for',(int)$data['division_id']),403,'Hanya dapat mengajukan untuk divisi Anda.');
        // Request and its audit record must succeed or fail as one unit.
        DB::transaction(function () use ($data, $period): void {
            $req = ExpenseRequest::create([...$data,'event_period_id'=>$period->id,'requested_by'=>auth()->id()]);
            Workspace::log('expense.requested','Pengajuan dana: '.$req->title,$req->id,'expense_request');
        });
        return back()->with('success','Pengajuan berhasil dikirim.');
    }

    public function review(Request $request, ExpenseRequest $expenseRequest)
    {
        Access::ensure('finance-approve',write:true);
        abort_unless($expenseRequest->event_period_id===Workspace::period()->id,404);
        $data=$request->validate(['status'=>['required',Rule::in(['approved','rejected'])],'review_note'=>['nullable','string','max:3000']]);

        // Serialize decisions for the same request. The status must be checked
        // AFTER locking, otherwise two reviewers can overwrite each other.
        DB::transaction(function () use ($expenseRequest, $data): void {
            $locked = ExpenseRequest::where('event_period_id', Workspace::period()->id)
                ->lockForUpdate()->findOrFail($expenseRequest->id);
            abort_unless($locked->status === 'pending', 422, 'Pengajuan ini sudah diputuskan.');
            $locked->update([...$data, 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
            Workspace::log('expense.reviewed',
                'Pengajuan dana '.$data['status'].': '.$locked->title,
                $locked->id, 'expense_request');
        });

        return back()->with('success','Keputusan pengajuan tersimpan.');
    }

    public function proof(FinancialTransaction $transaction)
    {
        Access::ensure('finance-view');
        abort_unless($transaction->event_period_id===Workspace::period()->id && $transaction->proof_file,404);
        abort_unless(Storage::disk('local')->exists($transaction->proof_file),404);
        return Storage::disk('local')->download($transaction->proof_file,$transaction->proof_name ?: 'bukti');
    }

    public function requests()
    {
        Access::ensure('expenses-request');
        $period=Workspace::period();
        $divisions=Division::where('event_period_id',$period->id)->get();
        $query=ExpenseRequest::where('event_period_id',$period->id);
        if (!Access::can(auth()->user(),'finance-view')) $query->where('requested_by',auth()->id());
        $requests=$query->latest()->paginate(20);
        return view('panitia.expenses',compact('period','divisions','requests'));
    }
}
