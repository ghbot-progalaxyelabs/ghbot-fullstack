import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';
import { api } from '@stonescript/api-client';

export interface User {
  id: string;
  email: string;
  name: string;
  avatarUrl: string;
}

@Injectable({
  providedIn: 'root'
})
export class AuthService {

  status: BehaviorSubject<boolean> = new BehaviorSubject(false);
  user: BehaviorSubject<User | null> = new BehaviorSubject<User | null>(null);
  token: BehaviorSubject<string | null> = new BehaviorSubject<string | null>(null);

  constructor() {
    // Check if user is already authenticated on service initialization
    const token = localStorage.getItem('authToken');
    if (token) {
      this.status.next(true);
      this.token.next(token);
      // Restore user data from localStorage
      const userData = this.getUserData();
      if (userData) {
        this.user.next(userData);
      }
    }
  }

  /**
   * Sign in with Google OAuth token
   * Issue #4: Update frontend AuthService to store JWT token
   *
   * @param googleToken JWT token from Google OAuth
   */
  async signIn(googleToken: string): Promise<void> {
    try {
      const response = await api.postAuthGoogleSignin({ googleToken });

      // Store JWT token
      localStorage.setItem('authToken', response.token);
      this.token.next(response.token);

      // Store user info
      this.setUserData(response.user);

      // Update authentication status
      this.status.next(true);
    } catch (error) {
      console.error('Sign in failed:', error);
      throw error;
    }
  }

  /**
   * Sign out the current user
   */
  signOut(): void {
    localStorage.removeItem('authToken');
    this.clearUserData();
    this.token.next(null);
    this.status.next(false);
  }

  /**
   * Get current JWT token
   */
  getToken(): string | null {
    return this.token.value || localStorage.getItem('authToken');
  }

  /**
   * Check if user is authenticated
   */
  isAuthenticated(): boolean {
    return this.status.value && !!this.getToken();
  }

  setUserData(user: User): void {
    this.user.next(user);
    localStorage.setItem('userData', JSON.stringify(user));
  }

  getUserData(): User | null {
    const userData = localStorage.getItem('userData');
    if (userData) {
      return JSON.parse(userData);
    }
    return null;
  }

  clearUserData(): void {
    this.user.next(null);
    localStorage.removeItem('userData');
  }

  getAuthToken(): string | null {
    return localStorage.getItem('authToken');
  }
}
