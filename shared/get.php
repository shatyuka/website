<?php
/*
Copyright 2019 whatever127

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

   http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
*/

function sortBySize($a, $b) {
    global $files;

    if ($files[$a]['size'] == $files[$b]['size']) {
        return 0;
    }

    return ($files[$a]['size'] < $files[$b]['size']) ? -1 : 1;
}

//Create aria2 download package with conversion script
function createUupConvertPackage(
    $url,
    $archiveName,
    $virtualEditions = 0,
    $desiredVE = array('Enterprise'),
    $moreOptions = []
) {
    $updates = isset($moreOptions['updates']) ? $moreOptions['updates'] : 0;
    $cleanup = isset($moreOptions['cleanup']) ? $moreOptions['cleanup'] : 0;
    $netfx = isset($moreOptions['netfx']) ? $moreOptions['netfx'] : 0;
    $esd = isset($moreOptions['esd']) ? $moreOptions['esd'] : 0;

    $type = $esd ? 'esd' : 'wim';

    $currDir = dirname(__FILE__).'/..';
    $time = gmdate("Y-m-d H:i:s T", time());
    $cmdScript = <<<SCRIPT
@echo off
rem Generated on $time

:: Proxy configuration
:: If you need to configure a proxy to be able to connect to the internet,
:: then you can do this by configuring the all_proxy environment variable.
:: By default this variable is empty, configuring aria2c to not use any proxy.
::
:: Usage: set "all_proxy=proxy_address"
:: For example: set "all_proxy=127.0.0.1:8888"
::
:: More information how to use this can be found at:
:: https://aria2.github.io/manual/en/html/aria2c.html#cmdoption-all-proxy
:: https://aria2.github.io/manual/en/html/aria2c.html#environment

set "all_proxy="

:: End of proxy configuration

cd /d "%~dp0"
if NOT "%cd%"=="%cd: =%" (
    echo Current directory contains spaces in its path.
    echo Please move or rename the directory to one not containing spaces.
    echo.
    pause
    goto :EOF
)

if "[%1]" == "[49127c4b-02dc-482e-ac4f-ec4d659b7547]" goto :START_PROCESS
REG QUERY HKU\S-1-5-19\Environment >NUL 2>&1 && goto :START_PROCESS

set command="""%~f0""" 49127c4b-02dc-482e-ac4f-ec4d659b7547
SETLOCAL ENABLEDELAYEDEXPANSION
set "command=!command:'=''!"

powershell -NoProfile Start-Process -FilePath '%COMSPEC%' ^
-ArgumentList '/c """!command!"""' -Verb RunAs 2>NUL

IF %ERRORLEVEL% GTR 0 (
    echo =====================================================
    echo This script needs to be executed as an administrator.
    echo =====================================================
    echo.
    pause
)

SETLOCAL DISABLEDELAYEDEXPANSION
goto :EOF

:START_PROCESS
set "aria2=files\\aria2c.exe"
set "a7z=files\\7zr.exe"
set "uupConv=files\\uup-converter-wimlib.7z"
set "aria2Script=files\\aria2_script.%random%.txt"
set "destDir=UUPs"

if NOT EXIST %aria2% goto :NO_ARIA2_ERROR
if NOT EXIST %a7z% goto :NO_FILE_ERROR
if NOT EXIST %uupConv% goto :NO_FILE_ERROR
if NOT EXIST ConvertConfig.ini goto :NO_FILE_ERROR

echo Extracting UUP converter...
"%a7z%" -x!ConvertConfig.ini -y x "%uupConv%" >NUL
echo.

echo Retrieving aria2 script...
"%aria2%" --no-conf --log-level=info --log="aria2_download.log" -o"%aria2Script%" --allow-overwrite=true --auto-file-renaming=false "$url"
if %ERRORLEVEL% GTR 0 call :DOWNLOAD_ERROR & exit /b 1
echo.

for /F "tokens=2 delims=:" %%i in ('findstr #UUPDUMP_ERROR: "%aria2Script%"') do set DETECTED_ERROR=%%i
if NOT [%DETECTED_ERROR%] == [] (
    echo Unable to retrieve data from Windows Update servers. Reason: %DETECTED_ERROR%
    echo If this problem persists, most likely the set you are attempting to download was removed from Windows Update servers.
    echo.
    pause
    goto :EOF
)

echo Attempting to download files...
"%aria2%" --no-conf --log-level=info --log="aria2_download.log" -x16 -s16 -j5 -c -R -d"%destDir%" -i"%aria2Script%"
if %ERRORLEVEL% GTR 0 call :DOWNLOAD_ERROR & exit /b 1

if EXIST convert-UUP.cmd goto :START_CONVERT
pause
goto :EOF

:START_CONVERT
call convert-UUP.cmd
goto :EOF

:NO_ARIA2_ERROR
echo We couldn't find %aria2% in current directory.
echo.
echo You can download aria2 from:
echo https://aria2.github.io/
echo.
pause
goto :EOF

:NO_FILE_ERROR
echo We couldn't find one of needed files for this script.
pause
goto :EOF

:DOWNLOAD_ERROR
echo.
echo We have encountered an error while downloading files.
pause
goto :EOF

:EOF

SCRIPT;

$shellScript = <<<SCRIPT
#!/bin/bash
#Generated on $time

# Proxy configuration
# If you need to configure a proxy to be able to connect to the internet,
# then you can do this by configuring the all_proxy environment variable.
# By default this variable is empty, configuring aria2c to not use any proxy.
#
# Usage: export all_proxy="proxy_address"
# For example: export all_proxy="127.0.0.1:8888"
#
# More information how to use this can be found at:
# https://aria2.github.io/manual/en/html/aria2c.html#cmdoption-all-proxy
# https://aria2.github.io/manual/en/html/aria2c.html#environment

export all_proxy=""

# End of proxy configuration

if ! which aria2c >/dev/null \\
|| ! which cabextract >/dev/null \\
|| ! which wimlib-imagex >/dev/null \\
|| ! which chntpw >/dev/null \\
|| ! which genisoimage >/dev/null \\
&& ! which mkisofs >/dev/null; then
  echo "One of required applications is not installed."
  echo "The following applications need to be installed to use this script:"
  echo " - aria2c"
  echo " - cabextract"
  echo " - wimlib-imagex"
  echo " - chntpw"
  echo " - genisoimage or mkisofs"
  echo ""
  if [ `uname` == "Linux" ]; then
    # Linux
    echo "If you use Debian or Ubuntu you can install these using:"
    echo "sudo apt-get install aria2 cabextract wimtools chntpw genisoimage"
    echo ""
    echo "If you use Arch Linux you can install these using:"
    echo "sudo pacman -S aria2 cabextract wimlib chntpw cdrtools"
  elif [ `uname` == "Darwin" ]; then
    # macOS
    echo "macOS requires Homebrew (https://brew.sh) to install the prerequisite software."
    echo "If you use Homebrew, you can install these using:"
    echo "brew tap sidneys/homebrew"
    echo "brew install aria2 cabextract wimlib cdrtools sidneys/homebrew/chntpw"
  fi
  exit 1
fi

destDir="UUPs"
tempScript="aria2_script.\$RANDOM.txt"

echo "Retrieving aria2 script..."
aria2c --no-conf --log-level=info --log="aria2_download.log" -o"\$tempScript" --allow-overwrite=true --auto-file-renaming=false "$url"
if [ $? != 0 ]; then
  echo "Failed to retrieve aria2 script"
  exit 1
fi

detectedError=`grep '#UUPDUMP_ERROR:' "\$tempScript" | sed 's/#UUPDUMP_ERROR://g'`
if [ ! -z \$detectedError ]; then
    echo "Unable to retrieve data from Windows Update servers. Reason: \$detectedError"
    echo "If this problem persists, most likely the set you are attempting to download was removed from Windows Update servers."
    exit 1
fi

echo ""
echo "Attempting to download files..."
aria2c --no-conf --log-level=info --log="aria2_download.log" -x16 -s16 -j5 -c -R -d"\$destDir" -i"\$tempScript"
if [ $? != 0 ]; then
  echo "We have encountered an error while downloading files."
  exit 1
fi

echo ""
if [ -e ./files/convert.sh ]; then
  chmod +x ./files/convert.sh
  ./files/convert.sh $type "\$destDir" $virtualEditions
fi

SCRIPT;

$desiredVirtualEditions = '';
$desiredVirtualEditionsLinux = '';
$index = 0;
foreach($desiredVE as $edition) {
    if($index > 0) {
        $desiredVirtualEditions .= ',';
        $desiredVirtualEditionsLinux .= ' ';
    }
    $desiredVirtualEditions .= $edition;
    $desiredVirtualEditionsLinux .= $edition;

    $index++;
}

    $convertConfig = <<<CONFIG
[convert-UUP]
AutoStart    =1
AddUpdates   =$updates
Cleanup      =$cleanup
ResetBase    =0
NetFx3       =$netfx
StartVirtual =$virtualEditions
wim2esd      =$esd
SkipISO      =0
SkipWinRE    =0
ForceDism    =0
RefESD       =0

[create_virtual_editions]
vAutoStart   =1
vDeleteSource=0
vPreserve    =0
vwim2esd     =$esd
vSkipISO     =0
vAutoEditions=$desiredVirtualEditions

CONFIG;

$convertConfigLinux = <<<CONFIG
VIRTUAL_EDITIONS_LIST='$desiredVirtualEditionsLinux'

CONFIG;

    $cmdScript = str_replace(["\r\n", "\r"], "\n", $cmdScript);
    $convertConfig = str_replace(["\r\n", "\r"], "\n", $convertConfig);
    $shellScript = str_replace(["\r\n", "\r"], "\n", $shellScript);
    $convertConfigLinux = str_replace(["\r\n", "\r"], "\n", $convertConfigLinux);

    $cmdScript = str_replace("\n", "\r\n", $cmdScript);
    $convertConfig = str_replace("\n", "\r\n", $convertConfig);

    $zip = new ZipArchive;
    $archive = @tempnam($currDir.'/tmp', 'zip');
    $open = $zip->open($archive, ZipArchive::CREATE+ZipArchive::OVERWRITE);

    if(!file_exists($currDir.'/autodl_files/aria2c.exe')) {
        die('aria2c.exe does not exist');
    }

    if(!file_exists($currDir.'/autodl_files/convert.sh')) {
        die('convert.sh does not exist');
    }

    if(!file_exists($currDir.'/autodl_files/convert_ve_plugin')) {
        die('convert_ve_plugin does not exist');
    }

    if(!file_exists($currDir.'/autodl_files/7zr.exe')) {
        die('7zr.exe does not exist');
    }

    if(!file_exists($currDir.'/autodl_files/uup-converter-wimlib.7z')) {
        die('uup-converter-wimlib.7z does not exist');
    }

    if($open === TRUE) {
        $zip->addFromString('uup_download_windows.cmd', $cmdScript);
        $zip->addFromString('uup_download_linux.sh', $shellScript);
        $zip->addFromString('uup_download_macos.sh', $shellScript);
        $zip->addFromString('ConvertConfig.ini', $convertConfig);
        $zip->addFromString('files/convert_config_linux', $convertConfigLinux);
        $zip->addFromString('files/convert_config_macos', $convertConfigLinux);
        $zip->addFile($currDir.'/autodl_files/aria2c.exe', 'files/aria2c.exe');
        $zip->addFile($currDir.'/autodl_files/convert.sh', 'files/convert.sh');
        $zip->addFile($currDir.'/autodl_files/convert_ve_plugin', 'files/convert_ve_plugin');
        $zip->addFile($currDir.'/autodl_files/7zr.exe', 'files/7zr.exe');
        $zip->addFile($currDir.'/autodl_files/uup-converter-wimlib.7z', 'files/uup-converter-wimlib.7z');
        $zip->close();
    } else {
        echo 'Failed to create archive.';
        die();
    }

    if($virtualEditions) {
        $suffix = '_virtual';
    } else {
        $suffix = '';
    }

    header('Content-Type: archive/zip');
    header('Content-Disposition: attachment; filename="'.$archiveName."_convert$suffix.zip\"");
    header('Content-Length: '.filesize($archive));

    $content = file_get_contents($archive);
    unlink($archive);

    echo $content;
}

//Create aria2 download package only
function createAria2Package($url, $archiveName) {
    $currDir = dirname(__FILE__).'/..';
    $time = gmdate("Y-m-d H:i:s T", time());

    $additionalNotice = "";
    if(strpos($archiveName, "lite")) {
        $additionalNotice = <<<TEXT

echo.
echo You downloaded a set of UUP files for Windows 10X.
echo After downloading, you should run "uup_convert_xml_10x.cmd" so that the files can be used with converters.
echo.

TEXT;
    }

    $cmdScript = <<<SCRIPT
@echo off
rem Generated on $time

:: Proxy configuration
:: If you need to configure a proxy to be able to connect to the internet,
:: then you can do this by configuring the all_proxy environment variable.
:: By default this variable is empty, configuring aria2c to not use any proxy.
::
:: Usage: set "all_proxy=proxy_address"
:: For example: set "all_proxy=127.0.0.1:8888"
::
:: More information how to use this can be found at:
:: https://aria2.github.io/manual/en/html/aria2c.html#cmdoption-all-proxy
:: https://aria2.github.io/manual/en/html/aria2c.html#environment

set "all_proxy="

:: End of proxy configuration

set "aria2=files\\aria2c.exe"
set "aria2Script=files\\aria2_script.%random%.txt"
set "destDir=UUPs"

cd /d "%~dp0"
if NOT EXIST %aria2% goto :NO_ARIA2_ERROR

echo Retrieving updated aria2 script...
"%aria2%" --no-conf --log-level=info --log="aria2_download.log" -o"%aria2Script%" --allow-overwrite=true --auto-file-renaming=false "$url"
if %ERRORLEVEL% GTR 0 call :DOWNLOAD_ERROR & exit /b 1

for /F "tokens=2 delims=:" %%i in ('findstr #UUPDUMP_ERROR: "%aria2Script%"') do set DETECTED_ERROR=%%i
if NOT [%DETECTED_ERROR%] == [] (
    echo Unable to retrieve data from Windows Update servers. Reason: %DETECTED_ERROR%
    echo If this problem persists, most likely the set you are attempting to download was removed from Windows Update servers.
    echo.
    pause
    goto :EOF
)

echo Attempting to download files...
"%aria2%" --no-conf --log-level=info --log="aria2_download.log" -x16 -s16 -j5 -c -R -d"%destDir%" -i"%aria2Script%"
if %ERRORLEVEL% GTR 0 call :DOWNLOAD_ERROR & exit /b 1
$additionalNotice
pause
goto EOF

:NO_ARIA2_ERROR
echo We couldn't find %aria2% in current directory.
echo.
echo You can download aria2 from:
echo https://aria2.github.io/
echo.
pause
goto EOF

:DOWNLOAD_ERROR
echo.
echo We have encountered an error while downloading files.
pause
goto EOF

:EOF

SCRIPT;

    $shellScript = <<<SCRIPT
#!/bin/bash
#Generated on $time

# Proxy configuration
# If you need to configure a proxy to be able to connect to the internet,
# then you can do this by configuring the all_proxy environment variable.
# By default this variable is empty, configuring aria2c to not use any proxy.
#
# Usage: export all_proxy="proxy_address"
# For example: export all_proxy="127.0.0.1:8888"
#
# More information how to use this can be found at:
# https://aria2.github.io/manual/en/html/aria2c.html#cmdoption-all-proxy
# https://aria2.github.io/manual/en/html/aria2c.html#environment

export all_proxy=""

# End of proxy configuration

if ! which aria2c >/dev/null; then
  echo "One of required applications is not installed."
  echo "The following applications need to be installed to use this script:"
  echo " - aria2c"
  echo ""
  echo "If you use Debian or Ubuntu you can install these using:"
  echo "sudo apt-get install aria2"
  echo ""
  echo "If you use Arch Linux you can install these using:"
  echo "sudo pacman -S aria2"
  exit 1
fi

destDir="UUPs"
tempScript="aria2_script.\$RANDOM.txt"

echo "Retrieving aria2 script..."
aria2c --no-conf --log-level=info --log="aria2_download.log" -o"\$tempScript" --allow-overwrite=true --auto-file-renaming=false "$url"
if [ $? != 0 ]; then
  echo "Failed to retrieve aria2 script"
  exit 1
fi

detectedError=`grep '#UUPDUMP_ERROR:' "\$tempScript" | sed 's/#UUPDUMP_ERROR://g'`
if [ ! -z \$detectedError ]; then
    echo "Unable to retrieve data from Windows Update servers. Reason: \$detectedError"
    echo "If this problem persists, most likely the set you are attempting to download was removed from Windows Update servers."
    exit 1
fi

echo ""
echo "Attempting to download files..."
aria2c --no-conf --log-level=info --log="aria2_download.log" -x16 -s16 -j5 -c -R -d"\$destDir" -i"\$tempScript"
if [ $? != 0 ]; then
  echo "We have encountered an error while downloading files."
  exit 1
fi

SCRIPT;

    $convertXml10XScript = <<<SCRIPT
@setlocal DisableDelayedExpansion
@echo off

if not exist "%~dp0UUPs" (
    echo ==== ERROR ====
    echo A set of UUP files for Windows 10X is required for this script to work.
    echo.
    echo Press any key to exit.
    pause >nul
    goto :eof
)

if not exist "%SystemRoot%\System32\WindowsPowerShell\\v1.0\powershell.exe" (
    echo ==== ERROR ====
    echo Windows PowerShell is required for this script to work.
    echo.
    echo Press any key to exit.
    pause >nul
    goto :eof
)

set "_batf=%~f0"
set "_batp=%_batf:'=''%"
set "_work=%~dp0"
if "%_work:~-1%"=="\" set "_work=%_work:~0,-1%"
setlocal EnableDelayedExpansion
pushd "!_work!\UUPs"

set _cpu=
if exist "Retail\AMD64\\fre\Microsoft-ModernPC*.cab" (
    set _cpu=amd64
) else if exist "Retail\ARM64\\fre\Microsoft-ModernPC*.cab" (
    set _cpu=arm64
) else if exist "Retail\\x86\\fre\Microsoft-ModernPC*.cab" (
    set _cpu=x86
)
if not defined _cpu (
    echo ==== ERROR ====
    echo Required cab files are not detected.
    echo Place the script next to [Retail] folder before running.
    echo.
    echo Press any key to exit.
    pause >nul
    goto :eof
)

if exist "FMFiles\*FM.xml" goto :skipFMs
md %SystemDrive%\_t10xml >nul 2>&1
expand -f:*.xml "Retail\%_cpu%\\fre\*fm~*.cab" %SystemDrive%\_t10xml >nul 2>&1
md FMFiles >nul 2>&1
for /f %%# in ('dir /b /s %SystemDrive%\_t10xml\*.xml') do copy /y %%# .\FMFiles >nul
rd /s /q %SystemDrive%\_t10xml >nul 2>&1

:skipFMs
set _type=Production
if exist "Retail\%_cpu%\\fre\*NonProductionFM*.cab" set _type=Test
md %SystemDrive%\_t10xml >nul 2>&1
expand -f:*.xml "Retail\%_cpu%\\fre\*%_type%*InboxCompDB*.cab" %SystemDrive%\_t10xml >nul 2>&1
for /f %%# in ('dir /b /s %SystemDrive%\_t10xml\*InboxCompDB*.xml 2^>nul') do copy /y %%# .\CompDB.xml >nul
rd /s /q %SystemDrive%\_t10xml >nul 2>&1
if not exist CompDB.xml (
    echo ==== Notice ====
    echo CompDB.xml file is not found.
    echo.
    echo Press any key to exit.
    pause >nul
    goto :eof
)

for /f %%# in ('dir /b FMFiles\*AppsFM*.xml') do copy /y FMFiles\%%# .\AppsDB.xml >nul
if not exist AppsDB.xml (
    del /f /q CompDB.xml >nul 2>&1
    echo ==== Notice ====
    echo AppsDB.xml file is not found.
    echo.
    echo Press any key to exit.
    pause >nul
    goto :eof
)

powershell.exe -nop -c "\$f=[IO.File]::ReadAllText('!_batp!') -split ':embed\:.*';iex (\$f[1])"
del /f /q CompDB.xml AppsDB.xml >nul 2>&1
echo ==== Done ====
echo.
echo You will find the FMFiles folder in %~dp0UUPs
echo.
echo Press any key to exit.
pause >nul
goto :eof

:embed:
[Environment]::CurrentDirectory = (Get-Location -PSProvider FileSystem).ProviderPath 
\$doc = [xml](gc ./CompDB.xml)
foreach (\$Package in \$doc.CompDB.AppX.AppXPackages.Package) {
    if (!\$Package.LicenseData) {continue}
    \$t = [IO.Path]::ChangeExtension(\$Package.Payload.PayloadItem.Path, 'xml')
    \$p = [IO.Path]::GetDirectoryName(\$t)
    \$d = \$Package.LicenseData."#cdata-section"
    \$d = \$Package.LicenseData.InnerText
    \$null = [IO.Directory]::CreateDirectory(\$p)
    [IO.File]::WriteAllText(\$t,\$d,[System.Text.Encoding]::ASCII)
}
\$xml = [xml](gc ./AppsDB.xml)
foreach (\$Package in \$xml.FeatureManifest.AppX.AppXPackages.PackageFile) {
    if (!\$Package.LicenseFile) {continue}
    \$fl = \$Package.LicenseFile
    \$fn = [IO.Path]::ChangeExtension(\$Package.Name, 'xml')
    if (\$fn.ToLower() -eq \$fl.ToLower()) {continue}
    \$fp = \$Package.Path | %{\$_ -replace "\\$\(mspackageroot\)\\\\",''}
    \$fn = Join-Path \$fp \$fn
    \$fl = Join-Path \$fp \$fl
    if (!(Test-Path \$fl) -and (Test-Path \$fn)) {Move-Item -Path \$fn -Destination \$fl -force}
    if ((Test-Path \$fl) -and (Test-Path \$fn)) {Remove-Item -Path \$fn -force}
}
:embed:

SCRIPT;

    $cmdScript = str_replace(["\r\n", "\r"], "\n", $cmdScript);
    $shellScript = str_replace(["\r\n", "\r"], "\n", $shellScript);
    $convertXml10XScript = str_replace(["\r\n", "\r"], "\n", $convertXml10XScript);
    $cmdScript = str_replace("\n", "\r\n", $cmdScript);
    $convertXml10XScript = str_replace("\n", "\r\n", $convertXml10XScript);

    $zip = new ZipArchive;
    $archive = @tempnam($currDir.'/tmp', 'zip');
    $open = $zip->open($archive, ZipArchive::CREATE+ZipArchive::OVERWRITE);

    if(!file_exists($currDir.'/autodl_files/aria2c.exe')) {
        die('aria2c.exe does not exist');
    }

    if($open === TRUE) {
        $zip->addFromString('uup_download_windows.cmd', $cmdScript);
        $zip->addFromString('uup_download_linux.sh', $shellScript);
        $zip->addFromString('uup_download_macos.sh', $shellScript);
        if(strpos($archiveName, "lite")) {
            $zip->addFromString('uup_convert_xml_10x.cmd', $convertXml10XScript);
        }
        $zip->addFile($currDir.'/autodl_files/aria2c.exe', 'files/aria2c.exe');
        $zip->close();
    } else {
        echo 'Failed to create archive.';
        die();
    }

    header('Content-Type: archive/zip');
    header('Content-Disposition: attachment; filename="'.$archiveName.'.zip"');
    header('Content-Length: '.filesize($archive));

    $content = file_get_contents($archive);
    unlink($archive);

    echo $content;
}
