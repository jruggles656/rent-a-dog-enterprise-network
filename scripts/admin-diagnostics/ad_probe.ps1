Get-ADUser -Filter * -Properties Enabled,LastLogonDate,MemberOf | Select-Object SamAccountName,Enabled,LastLogonDate | Sort-Object SamAccountName | Format-Table -AutoSize
Write-Output "--- admin/privileged group memberships ---"
foreach ($g in @("Domain Admins","Enterprise Admins","Administrators","Schema Admins")) {
    Write-Output "### $g"
    try { Get-ADGroupMember -Identity $g | Select-Object Name,SamAccountName,objectClass | Format-Table -AutoSize } catch { Write-Output "(no such group or error)" }
}
