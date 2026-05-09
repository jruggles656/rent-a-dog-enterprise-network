
$ErrorActionPreference = "Stop"

Write-Output "======== PHASE 1.A: Capture current policy (for audit trail) ========"
Get-ADDefaultDomainPasswordPolicy | Format-List MinPasswordLength,LockoutThreshold,LockoutDuration,MaxPasswordAge,ComplexityEnabled,PasswordHistoryCount

Write-Output "======== PHASE 1.B: Bump MinPasswordLength 8 -> 14 ========"
Set-ADDefaultDomainPasswordPolicy -Identity nestlerteam6.local -MinPasswordLength 14
Write-Output "-- new policy --"
Get-ADDefaultDomainPasswordPolicy | Format-List MinPasswordLength,LockoutThreshold,LockoutDuration

Write-Output "======== PHASE 1.C: Rotate allegra ========"
$allegraPw = ConvertTo-SecureString "[REDACTED]" -AsPlainText -Force
Set-ADAccountPassword -Identity allegra -NewPassword $allegraPw -Reset
Set-ADUser allegra -ChangePasswordAtLogon $false -PasswordNeverExpires $false
Get-ADUser allegra -Properties Enabled,PasswordLastSet,PasswordNeverExpires | Format-List SamAccountName,Enabled,PasswordLastSet,PasswordNeverExpires

Write-Output "======== PHASE 1.D: Re-enable james with new password ========"
$jamesPw = ConvertTo-SecureString "[REDACTED]" -AsPlainText -Force
Set-ADAccountPassword -Identity james -NewPassword $jamesPw -Reset
Set-ADUser james -Enabled $true -ChangePasswordAtLogon $false -PasswordNeverExpires $false
Get-ADUser james -Properties Enabled,PasswordLastSet,PasswordNeverExpires | Format-List SamAccountName,Enabled,PasswordLastSet,PasswordNeverExpires

Write-Output "======== PHASE 1.E: Delete obsolete users ========"
foreach ($u in @("botnet","jason","oscar")) {
    try {
        Remove-ADUser -Identity $u -Confirm:$false -ErrorAction Stop
        Write-Output "  DELETED: $u"
    } catch {
        Write-Output "  FAIL delete $u : $_"
    }
}

Write-Output "======== PHASE 1.F: Post-state inventory ========"
Get-ADUser -Filter * -Properties Enabled,LastLogonDate | Select-Object SamAccountName,Enabled,LastLogonDate | Sort-Object SamAccountName | Format-Table -AutoSize

Write-Output "======== PHASE 1.G: Privileged groups check ========"
foreach ($g in @("Domain Admins","Enterprise Admins","Schema Admins","Administrators")) {
    Write-Output "--- $g ---"
    Get-ADGroupMember -Identity $g | Select-Object Name,SamAccountName | Format-Table -AutoSize
}
