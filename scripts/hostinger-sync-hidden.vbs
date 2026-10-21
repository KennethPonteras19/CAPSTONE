Option Explicit

' A GUI host starts PowerShell hidden before a terminal window can be created.
' Wait for completion so Task Scheduler tracks the watcher and its exit code.
Dim shell, command, result
If WScript.Arguments.Count <> 2 Then WScript.Quit 2

Set shell = CreateObject("WScript.Shell")
command = Chr(34) & WScript.Arguments(0) & Chr(34) & _
    " -NoProfile -NonInteractive -WindowStyle Hidden -File " & _
    Chr(34) & WScript.Arguments(1) & Chr(34) & " -Watch"
result = shell.Run(command, 0, True)
WScript.Quit result
