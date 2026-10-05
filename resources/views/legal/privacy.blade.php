@php
    $app = $appSettings['app_name'];
    $company = $appSettings['company_name'];
@endphp
<!doctype html>
<html lang="en">
<head>
    @include('layouts.head', ['title' => 'Privacy Policy'])
    @vite('resources/js/auth.js')
</head>
<body>
<main class="legal-page">
    <a href="{{ route('login') }}" class="legal-back"><i class="bi bi-arrow-left me-1"></i>Back to sign in</a>

    <h1>Privacy Policy</h1>
    <p class="legal-meta">{{ $app }} · {{ $company }}</p>

    <p>This policy explains what data the <strong>{{ $app }}</strong> application (mobile app and admin panel) collects,
        how that data is used, and how it is protected. The application is provided by {{ $company }}
        solely for recording the attendance of project employees.</p>

    <h2>1. Data we collect</h2>
    <ul>
        <li><strong>Account &amp; employment data</strong>: name, username, employee ID number, position, project, and work shift.</li>
        <li><strong>Location (GPS)</strong>: coordinates and accuracy, collected <em>only when</em> you check in or check out.
            The app does not track your location in the background.</li>
        <li><strong>Attendance photos</strong>: photos taken with the camera at check-in and check-out as proof of attendance.</li>
        <li><strong>Device information</strong>: device ID, platform, model, operating system version, app version, and
            indicators of mock (fake) location usage, used to bind your account to your device and to prevent fraud.</li>
        <li><strong>Attendance &amp; leave data</strong>: attendance times, attendance status, notes, and leave/sick/time-off
            requests together with their attachments.</li>
        <li><strong>Offline data</strong>: when there is no signal, attendance records are stored temporarily on your device,
            sent automatically once a connection is available, and then removed from the device.</li>
    </ul>

    <h2>2. How we use your data</h2>
    <p>Data is used only to record and verify attendance, validate that you are at the project work site, process approvals
        for leave and off-site attendance, and prepare attendance reports for project administration and payroll.
        Your data is <strong>never sold</strong> and is not used for advertising.</p>

    <h2>3. Who can access your data</h2>
    <p>Your data can only be accessed by the administrators and Team Leaders of the project you are assigned to, and by the
        signatories of the project's attendance reports. Data is not shared with third parties unless required by law.</p>

    <h2>4. Storage &amp; security</h2>
    <p>Data is stored on servers owned or managed by {{ $company }}. Photos are kept in private storage that is not publicly
        accessible, and all communication between the app and the server is encrypted (HTTPS). Attendance data is retained
        for as long as necessary for project administration and applicable legal obligations. Report files generated in the
        admin panel are deleted automatically after {{ config('presensi.report_retention_days') }} days.</p>

    <h2>5. Device permissions</h2>
    <ul>
        <li><strong>Location</strong>: to confirm that attendance is recorded within the project area.</li>
        <li><strong>Camera</strong>: to take attendance photos.</li>
    </ul>
    <p>You can revoke these permissions at any time in your device settings; however, attendance cannot be recorded without them.</p>

    <h2>6. Your rights</h2>
    <p>You have the right to request access to, correction of, or deletion of your personal data in accordance with
        Indonesian Law No. 27 of 2022 on Personal Data Protection. Corrections to attendance records can be requested
        through your Team Leader or the project administrator.</p>

    <h2>7. Changes to this policy</h2>
    <p>This policy may be updated from time to time. The latest version is always available on this page.</p>

    <h2>8. Contact</h2>
    <p>Questions about privacy can be directed to the administrator or the HR department of {{ $company }}.</p>
</main>
</body>
</html>
