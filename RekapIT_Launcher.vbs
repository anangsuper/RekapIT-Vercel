Dim WshShell
Set WshShell = CreateObject("WScript.Shell")
WshShell.Run chr(34) & WshShell.CurrentDirectory & "\RekapIT_Launcher.bat" & chr(34), 0
Set WshShell = Nothing
