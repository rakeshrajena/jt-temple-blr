param(
    [Parameter(Mandatory = $true)][string]$Source,
    [Parameter(Mandatory = $true)][string]$Destination
)
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName PresentationCore
$item = Get-Item -LiteralPath $Source
$uri = New-Object System.Uri($item.FullName)
$decoder = [System.Windows.Media.Imaging.BitmapDecoder]::Create(
    $uri,
    [System.Windows.Media.Imaging.BitmapCreateOptions]::PreservePixelFormat,
    [System.Windows.Media.Imaging.BitmapCacheOption]::OnLoad
)
$frame = $decoder.Frames[0]
$maxSide = 256
$longest = [Math]::Max($frame.PixelWidth, $frame.PixelHeight)
$bitmap = $frame
if ($longest -gt $maxSide) {
    $scale = $maxSide / $longest
    $transform = New-Object System.Windows.Media.ScaleTransform($scale, $scale)
    $bitmap = New-Object System.Windows.Media.Imaging.TransformedBitmap($frame, $transform)
}
$encoder = New-Object System.Windows.Media.Imaging.PngBitmapEncoder
$encoder.Frames.Add([System.Windows.Media.Imaging.BitmapFrame]::Create($bitmap))
$stream = [System.IO.File]::Open($Destination, [System.IO.FileMode]::Create)
try {
    $encoder.Save($stream)
} finally {
    $stream.Dispose()
}
