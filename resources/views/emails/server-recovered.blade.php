<h2>✅ Server Recovered</h2>

<p>Hello,</p>

<p>The following server or website is back online:</p>

<ul>
    <li><strong>Server:</strong> {{ $server->name }}</li>
    <li><strong>Host:</strong> {{ $server->host }}</li>
    <li><strong>Website:</strong> {{ $server->website_url ?? 'N/A' }}</li>
    <li><strong>Status:</strong> ONLINE</li>
    <li><strong>Time:</strong> {{ now()->format('Y-m-d H:i:s') }}</li>
</ul>

<p>Regards,<br>Webscept Server Monitoring</p>