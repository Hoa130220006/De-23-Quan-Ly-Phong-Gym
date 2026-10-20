# ==========================================================
# TITAN FITNESS - AUTOMATED SYSTEM HEALTH & INTEGRATION TEST
# ==========================================================

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "   KIEM TRA TU DONG HE THONG GYM MANAGEMENT & MONITORING  " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

# 1. Kiem tra trang thai containers
Write-Host "`n[1/5] Kiem tra trang thai cac Docker Containers..." -ForegroundColor Yellow
$containers = docker ps --filter "name=gym_" --format "{{.Names}}: {{.Status}}"
if ($containers) {
    foreach ($line in $containers) {
        Write-Host "  [OK] $line" -ForegroundColor Green
    }
} else {
    Write-Host "  [ERR] Khong tim thay container gym nao dang chay!" -ForegroundColor Red
}

# 2. Kiem tra HTTP Port 8088 redirect sang HTTPS
Write-Host "`n[2/5] Kiem tra Nginx HTTP (8088) -> HTTPS (443) redirect..." -ForegroundColor Yellow
try {
    $req = [System.Net.WebRequest]::Create("http://localhost:8088")
    $req.AllowAutoRedirect = $false
    $resp = $req.GetResponse()
    $statusCode = [int]$resp.StatusCode
    $location = $resp.Headers["Location"]
    Write-Host "  [OK] HTTP 8088 tra ve ma $statusCode (Redirect den $location)" -ForegroundColor Green
    $resp.Close()
} catch [System.Net.WebException] {
    if ($_.Response) {
        $statusCode = [int]$_.Response.StatusCode
        $location = $_.Response.Headers["Location"]
        Write-Host "  [OK] HTTP 8088 tra ve ma $statusCode (Redirect den $location)" -ForegroundColor Green
    } else {
        Write-Host "  [WARN] Loi ket noi HTTP: $($_.Message)" -ForegroundColor DarkYellow
    }
}

# 3. Kiem tra Nginx HTTPS (Port 443, SSL tu ky)
Write-Host "`n[3/5] Kiem tra Nginx HTTPS (Port 443)..." -ForegroundColor Yellow
try {
    [System.Net.ServicePointManager]::ServerCertificateValidationCallback = {$true}
    $wc = New-Object System.Net.WebClient
    $html = $wc.DownloadString("https://localhost/login.php")
    if ($html -match "TITAN FITNESS") {
        Write-Host "  [OK] HTTPS Port 443 hoat dong chinh xac! Trang login nap thanh cong." -ForegroundColor Green
    } else {
        Write-Host "  [WARN] HTTPS ket noi duoc nhung khong tim thay tieu de TITAN FITNESS." -ForegroundColor Yellow
    }
} catch {
    Write-Host "  [ERR] Khong the truy cap HTTPS: $($_.Message)" -ForegroundColor Red
}

# 4. Kiem tra Prometheus (9090) va Grafana (3000)
Write-Host "`n[4/5] Kiem tra Prometheus Server va Grafana..." -ForegroundColor Yellow
try {
    $wc = New-Object System.Net.WebClient
    $prom = $wc.DownloadString("http://localhost:9090/-/healthy")
    Write-Host "  [OK] Prometheus Server: HEALTHY (Mã 200)" -ForegroundColor Green
} catch {
    Write-Host "  [ERR] Prometheus chua san sang: $($_.Message)" -ForegroundColor Red
}

try {
    $wc = New-Object System.Net.WebClient
    $graf = $wc.DownloadString("http://localhost:3000/api/health")
    Write-Host "  [OK] Grafana Dashboard Server: HEALTHY (Mã 200)" -ForegroundColor Green
} catch {
    Write-Host "  [ERR] Grafana chua san sang: $($_.Message)" -ForegroundColor Red
}

# 5. Kiem tra Loki (3100)
Write-Host "`n[5/5] Kiem tra Loki Log Aggregator (Port 3100)..." -ForegroundColor Yellow
try {
    $loki = (Invoke-WebRequest -Uri "http://localhost:3100/ready" -UseBasicParsing -ErrorAction Stop).Content.Trim()
    if ($loki -eq "ready") {
        Write-Host "  [OK] Loki Server: READY (Mã 200 - Trạng thái: $loki)" -ForegroundColor Green
    } else {
        Write-Host "  [WARN] Loki tra ve: $loki" -ForegroundColor Yellow
    }
} catch {
    Write-Host "  [ERR] Loki chua san sang: $($_.Message)" -ForegroundColor Red
}

Write-Host "`n==========================================================" -ForegroundColor Cyan
Write-Host "   HOAN THANH KIEM TRA TOAN BO HE THONG" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
