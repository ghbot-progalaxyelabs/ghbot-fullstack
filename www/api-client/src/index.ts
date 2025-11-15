/**
 * Auto-generated TypeScript API Client
 * Generated from PHP routes
 *
 * DO NOT EDIT MANUALLY - Regenerate using: php generate client
 */

// ============================================================================
// Type Definitions
// ============================================================================

export interface GetWebsiteRequest {
  id: string;
  userId?: string | null;
}

export interface GetWebsiteResponse {
  id: string;
  name: string;
  type: string;
  content: object;
  settings: object;
  status: string;
  createdAt: string;
  updatedAt: string;
}

export interface WebsitesRequest {
  name: string;
  type: string;
  userId?: string | null;
}

export interface WebsitesResponse {
  id: string;
  name: string;
  type: string;
  status: string;
  createdAt: string;
}

export interface UpdateWebsiteRequest {
  id: string;
  name?: string | null;
  content?: object | null;
  settings?: object | null;
  userId?: string | null;
}

export interface UpdateWebsiteResponse {
  id: string;
  updatedAt: string;
}

export interface GoogleSigninRequest {
  googleToken: string;
}

export interface GoogleSigninResponse {
  token: string;
  user: {
    id: string;
    email: string;
    name: string;
    avatarUrl: string;
  };
}


// ============================================================================
// API Client
// ============================================================================

/**
 * Get authentication token from localStorage
 * Issue #5: Update API client to include Authorization header
 */
function getAuthToken(): string | null {
  return localStorage.getItem('authToken');
}

/**
 * Get headers with optional authentication
 */
function getHeaders(includeAuth: boolean = true): HeadersInit {
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };

  if (includeAuth) {
    const token = getAuthToken();
    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }
  }

  return headers;
}

export const api = {
  async postAuthGoogleSignin(data: GoogleSigninRequest): Promise<GoogleSigninResponse> {
    const response = await fetch('/auth/google-signin', {
      method: 'POST',
      headers: getHeaders(false), // Don't include auth for signin
      body: JSON.stringify(data),
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const json = await response.json();
    return json.data;
  },

  async getWebsiteById(id: string): Promise<GetWebsiteResponse> {
    const response = await fetch(`/websites/${id}`, {
      method: 'GET',
      headers: getHeaders(),
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const json = await response.json();
    return json.data;
  },

  async postWebsites(data: WebsitesRequest): Promise<WebsitesResponse> {
    const response = await fetch('/websites', {
      method: 'POST',
      headers: getHeaders(),
      body: JSON.stringify(data),
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const json = await response.json();
    return json.data;
  },

  async updateWebsiteById(id: string, data: UpdateWebsiteRequest): Promise<UpdateWebsiteResponse> {
    const response = await fetch(`/websites/${id}`, {
      method: 'PUT',
      headers: getHeaders(),
      body: JSON.stringify(data),
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const json = await response.json();
    return json.data;
  },

};

export default api;
