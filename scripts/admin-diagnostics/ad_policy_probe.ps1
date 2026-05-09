
Write-Output "=== Default Domain Password Policy ==="
Get-ADDefaultDomainPasswordPolicy | Format-List ComplexityEnabled,LockoutDuration,LockoutObservationWindow,LockoutThreshold,MaxPasswordAge,MinPasswordAge,MinPasswordLength,PasswordHistoryCount,ReversibleEncryptionEnabled

Write-Output "=== Fine-grained password policies (PSOs) ==="
try { Get-ADFineGrainedPasswordPolicy -Filter * | Format-List Name,Precedence,AppliesTo,MinPasswordLength,LockoutThreshold,LockoutDuration } catch { Write-Output "(none or error)" }

Write-Output "=== User detail: allegra ==="
Get-ADUser allegra -Properties Enabled,PasswordNeverExpires,PasswordExpired,PasswordLastSet,LockoutTime,CannotChangePassword,MemberOf,LastLogonDate,BadLogonCount | Format-List SamAccountName,Enabled,PasswordNeverExpires,PasswordExpired,PasswordLastSet,LockoutTime,CannotChangePassword,LastLogonDate,BadLogonCount,MemberOf

Write-Output "=== User detail: james ==="
Get-ADUser james -Properties Enabled,PasswordNeverExpires,PasswordExpired,PasswordLastSet,LockoutTime,CannotChangePassword,MemberOf,LastLogonDate,BadLogonCount | Format-List SamAccountName,Enabled,PasswordNeverExpires,PasswordExpired,PasswordLastSet,LockoutTime,CannotChangePassword,LastLogonDate,BadLogonCount,MemberOf

Write-Output "=== Group members: Domain Admins / Enterprise Admins ==="
foreach ($g in @("Domain Admins","Enterprise Admins","Schema Admins","Administrators")) {
    Write-Output "--- $g ---"
    try { Get-ADGroupMember -Identity $g -ErrorAction Stop | Select-Object Name,SamAccountName,objectClass | Format-Table -AutoSize } catch { Write-Output "error: $_" }
}

Write-Output "=== net accounts (legacy view of effective policy) ==="
net accounts
