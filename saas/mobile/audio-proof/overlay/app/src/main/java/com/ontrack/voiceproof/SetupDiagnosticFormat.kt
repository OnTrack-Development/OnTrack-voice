package com.ontrack.voiceproof

/** Fixed, bounded diagnostic commands; never accepts arbitrary shell input. */
object SetupDiagnosticFormat {
    const val PERMISSION = "android.permission.WRITE_SECURE_SETTINGS"
    private fun validate(pkg: String, user: Int) {
        require(pkg.matches(Regex("[A-Za-z_][A-Za-z0-9_]*(\\.[A-Za-z_][A-Za-z0-9_]*)+")))
        require(user >= 0)
    }
    fun grant(pkg: String, user: Int): String {
        validate(pkg, user)
        return "pm grant --user $user $pkg $PERMISSION 2>&1; echo pm-exit=\$?"
    }
    fun readBack(pkg: String, user: Int): String {
        validate(pkg, user)
        return "dumpsys package $pkg | grep -F -e 'Package [$pkg]' -e 'User $user:' -e 'WRITE_SECURE_SETTINGS'"
    }
}
