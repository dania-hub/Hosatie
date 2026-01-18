export function useAuthorization(user) {
  const hasRole = (role) => {
    if (!user || !user.roles) return false;
    return user.roles.includes(role);
  };

  const hasGlobalRole = () => {
    if (!user || !user.roles) return false;
    return user.roles.includes('SUPER_ADMIN');
  };

  const hasPermission = (permission) => {
    if (!user) return false;
    if (hasGlobalRole()) return true;
    if (!user.permissions) return false;
    return user.permissions.includes(permission);
  };

  return {
    hasRole,
    hasPermission,
    hasGlobalRole,
  };
}
