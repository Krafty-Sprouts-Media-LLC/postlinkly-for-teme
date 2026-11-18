<#
 .SYNOPSIS
  Checks Postlinkly against a real Teme development site, using only the public console.

 .DESCRIPTION
  Finds and enables the plugin (agreeing to what it asks for), runs its waiting migrations,
  confirms its three tables exist, confirms the shared engine (postlinkly/core) loads inside
  the site, and runs the site's health check. It changes nothing but this plugin's own state.

  Each artisan call runs with a time limit, because a Teme command can wait forever on its
  database connection (temecms/teme#543). The site's .env sets PGCONNECT_TIMEOUT for the same
  reason.

 .PARAMETER Site
  The folder of the Teme development site. Defaults to Postlinkly's own Herd site.

 .PARAMETER Seconds
  The most each artisan call may take.

 .PARAMETER As
  The email of the site administrator the plugin's scheduled work runs as. Teme won't enable a
  plugin with schedules from the command line without one. When given, the plugin is disabled
  first, so it is enabled again as that person.
#>
param(
    [string] $Site = 'C:\Users\kings\Herd\postlinkly-teme',
    [int] $Seconds = 120,
    [string] $As = ''
)

$ErrorActionPreference = 'Stop'

function Artisan([string[]] $Arguments) {
    $job = Start-Job -ScriptBlock { param($dir, $argv) Set-Location $dir; & php artisan @argv 2>&1; "EXIT:$LASTEXITCODE" } -ArgumentList $Site, $Arguments
    if (-not (Wait-Job $job -Timeout $Seconds)) {
        Stop-Job $job
        throw "php artisan $($Arguments -join ' ') did not finish within $Seconds seconds."
    }
    $lines = @(Receive-Job $job)
    $exit = ($lines | Where-Object { "$_" -like 'EXIT:*' } | Select-Object -Last 1) -replace 'EXIT:', ''
    $output = $lines | Where-Object { "$_" -notlike 'EXIT:*' }
    return [pscustomobject]@{ Output = $output; Exit = [int]$exit }
}

function Step([string] $Name, [string[]] $Arguments) {
    Write-Host "== $Name"
    $result = Artisan $Arguments
    $result.Output | ForEach-Object { Write-Host "  $_" }
    if ($result.Exit -ne 0) { throw "$Name failed (exit $($result.Exit))." }
}

function Ask([string] $Php) {
    $result = Artisan @('tinker', "--execute=$Php")
    return ($result.Output | Where-Object { "$_".Trim() -ne '' } | Select-Object -Last 1)
}

Step 'Plugin is found' @('teme:plugins')

if ($As -eq '') {
    throw 'Postlinkly runs work on a schedule: pass -As with the email of a site administrator.'
}

# Disabling first may fail when the plugin isn't enabled yet; that's fine.
$null = Artisan @('teme:plugins:disable', 'postlinkly/teme')
Step 'Enable as the administrator its schedules run as' @('teme:plugins:enable', 'postlinkly/teme', '--agree', "--as=$As")
Step 'Run waiting database changes' @('teme:plugins:migrate', 'postlinkly/teme')

Write-Host '== Tables exist'
foreach ($table in 'postlinkly_items', 'postlinkly_stems', 'postlinkly_suggestions') {
    $answer = Ask "echo Illuminate\Support\Facades\Schema::hasTable('$table') ? 'yes' : 'no';"
    if ($answer -ne 'yes') { throw "Table $table is missing." }
    Write-Host "  $table"
}

Write-Host '== The shared engine loads inside the site'
$core = Ask "echo class_exists('Postlinkly\\CoreVersion') ? Postlinkly\CoreVersion::VERSION : 'missing';"
if ($core -eq 'missing') {
    throw "postlinkly/core does not load inside the site. Teme doesn't autoload a folder plugin's own vendor/ (FINDINGS H1); install the plugin through Composer instead."
}
Write-Host "  postlinkly/core $core"

Step 'Health check' @('teme:doctor')
Write-Host 'Postlinkly is enabled, its tables exist and its engine loads.'
