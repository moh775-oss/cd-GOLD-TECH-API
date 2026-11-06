<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>دليل الحسابات - GOLD TECH</title>
    <style>
        *{box-sizing:border-box}
        body{font-family:Tahoma,Arial;background:#f4f6fa;margin:0;padding:25px;color:#253047}
        .container{max-width:1200px;margin:auto}
        .header{background:#fff;padding:22px;border-radius:12px;margin-bottom:20px}
        h2{margin:0 0 12px;color:#172554}
        .status{padding:13px;border-radius:8px;margin-bottom:15px;background:#e0f2fe;color:#075985}
        .success{background:#dcfce7;color:#166534}
        .danger{background:#fee2e2;color:#991b1b}
        .table-wrap{background:#fff;border-radius:12px;overflow:auto}
        table{width:100%;border-collapse:collapse;min-width:850px}
        th,td{padding:13px 12px;text-align:right;border-bottom:1px solid #edf0f5}
        th{background:#172554;color:#fff;white-space:nowrap}
        tr:hover td{background:#f8fafc}
        .count{font-size:14px;color:#64748b}
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h2>دليل الحسابات - GOLD TECH</h2>
        @if($tableExists === true)
            <div class="status success">تم الاتصال بقاعدة البيانات والجدول موجود بنجاح.</div>
            <div class="count">عدد الحسابات الموجودة: {{ $accounts->count() }}</div>
        @elseif($tableExists === false)
            <div class="status danger">اتصال قاعدة البيانات يعمل، لكن جدول accounts_template غير موجود.</div>
        @else
            <div class="status danger">{{ $error }}</div>
        @endif
    </div>

    @if($tableExists === true)
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>رقم الحساب</th>
                        <th>اسم الحساب</th>
                        <th>الحساب الأب</th>
                        <th>نوع الحساب</th>
                        <th>المستوى</th>
                        <th>القائمة</th>
                        <th>طبيعة الحساب</th>
                        <th>الترتيب</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $account)
                        <tr>
                            <td>{{ $account->acc_no }}</td>
                            <td>{{ $account->name }}</td>
                            <td>{{ $account->father_acc_no }}</td>
                            <td>{{ $account->account_type }}</td>
                            <td>{{ $account->account_level }}</td>
                            <td>{{ $account->final_account }}</td>
                            <td>{{ $account->account_nature }}</td>
                            <td>{{ $account->sort_order }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align:center">الجدول موجود لكنه لا يحتوي على حسابات.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
</body>
</html>
