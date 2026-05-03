<h2>🚨 Server Down Alert</h2>

<p>Hello,</p>

<p>The following server or website is currently down:</p>

<ul>
    <li><strong>Server:</strong> {{ $server->name }}</li>
    <li><strong>Host:</strong> {{ $server->host }}</li>
    <li><strong>Website:</strong> {{ $server->website_url ?? 'N/A' }}</li>
    <li><strong>Status:</strong> DOWN</li>
    <li><strong>Time:</strong> {{ now()->format('Y-m-d H:i:s') }}</li>
</ul>

<p>Our monitoring system has detected the issue and action is required.</p>

<p>Regards,<br>Webscept Server Monitoring</p>