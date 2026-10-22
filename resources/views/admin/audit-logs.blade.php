<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>API Audit Logs</title>
    <style>
        body { margin: 0; padding: 32px; background: #111827; color: #f9fafb; font: 14px system-ui, sans-serif; }
        main { max-width: 1200px; margin: auto; }
        h1 { margin-top: 0; }
        form { display: flex; flex-wrap: wrap; gap: 8px; margin: 20px 0; }
        input, button { padding: 10px; border: 1px solid #4b5563; border-radius: 6px; background: #1f2937; color: inherit; }
        input { min-width: 150px; }
        input[name=token] { min-width: 320px; flex: 1; }
        button { cursor: pointer; background: #2563eb; border: 0; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; background: #1f2937; }
        th, td { padding: 10px; border-bottom: 1px solid #374151; text-align: left; vertical-align: top; }
        pre { max-width: 320px; max-height: 120px; overflow: auto; white-space: pre-wrap; }
        #message { color: #fbbf24; }
    </style>
</head>
<body>
<main>
    <h1>API Audit Logs</h1>
    <p>Masukkan Sanctum token akun admin. Token disimpan hanya di session browser.</p>
    <form id="filters">
        <input name="token" type="password" autocomplete="off" placeholder="Admin bearer token" required>
        <input name="method" placeholder="Method, contoh GET">
        <input name="path" placeholder="Endpoint, contoh restaurants">
        <input name="status" type="number" placeholder="HTTP status">
        <input name="date_from" type="date">
        <input name="date_to" type="date">
        <button type="submit">Muat log</button>
    </form>
    <p id="message" role="status"></p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Waktu</th><th>User</th><th>Request</th><th>IP / User-Agent</th><th>Status</th><th>Durasi</th><th>Headers tersaring</th><th>Body tersaring</th></tr></thead>
            <tbody id="rows"></tbody>
        </table>
    </div>
</main>
<script>
    const form = document.getElementById('filters');
    const rows = document.getElementById('rows');
    const message = document.getElementById('message');
    const tokenInput = form.elements.token;
    tokenInput.value = sessionStorage.getItem('adminToken') || '';

    function cell(value, json = false) {
        const td = document.createElement('td');
        if (json) {
            const pre = document.createElement('pre');
            pre.textContent = JSON.stringify(value ?? {}, null, 2);
            td.append(pre);
        } else {
            td.textContent = value ?? '-';
        }
        return td;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const token = tokenInput.value.trim();
        sessionStorage.setItem('adminToken', token);
        const params = new URLSearchParams();
        for (const key of ['method', 'path', 'status', 'date_from', 'date_to']) {
            const value = form.elements[key].value.trim();
            if (value) params.set(key, value);
        }
        params.set('per_page', '100');
        message.textContent = 'Memuat log...';
        rows.replaceChildren();

        try {
            const response = await fetch(`/api/admin/audit-logs?${params}`, {
                headers: { Accept: 'application/json', Authorization: `Bearer ${token}` }
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || `HTTP ${response.status}`);
            const records = payload.data.data || [];
            for (const log of records) {
                const tr = document.createElement('tr');
                tr.append(
                    cell(log.created_at),
                    cell(log.user ? `${log.user.name} (${log.user.email})` : 'Guest'),
                    cell(`${log.method} ${log.path}`),
                    cell(`${log.ip_address || '-'}\n${log.user_agent || ''}`),
                    cell(log.response_status),
                    cell(`${log.duration_ms ?? '-'} ms`),
                    cell(log.request_headers, true),
                    cell(log.request_body, true)
                );
                rows.append(tr);
            }
            message.textContent = `${records.length} log ditampilkan.`;
        } catch (error) {
            message.textContent = `Gagal memuat log: ${error.message}`;
        }
    });
</script>
</body>
</html>
