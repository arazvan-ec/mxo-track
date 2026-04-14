import { createContext, useContext, type ReactNode } from 'react';
import { useUserPreferences, type UserPreferences } from '../api/hooks/useUserPreferences';

interface UserPreferencesContextValue {
  preferences: UserPreferences | undefined;
  isLoading: boolean;
}

const UserPreferencesContext = createContext<UserPreferencesContextValue>({
  preferences: undefined,
  isLoading: true,
});

export function useUserPreferencesContext(): UserPreferencesContextValue {
  return useContext(UserPreferencesContext);
}

interface UserPreferencesProviderProps {
  children: ReactNode;
}

export function UserPreferencesProvider({ children }: UserPreferencesProviderProps) {
  const { data, isLoading } = useUserPreferences();

  return (
    <UserPreferencesContext.Provider value={{ preferences: data, isLoading }}>
      {children}
    </UserPreferencesContext.Provider>
  );
}
