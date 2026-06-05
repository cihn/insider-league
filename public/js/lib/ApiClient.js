/**
 * Thin wrapper around fetch for the league JSON API. Attaches the CSRF token
 * to every mutating request and normalises error handling.
 */
export class ApiClient {
    constructor() {
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    }

    async request(method, url, body) {
        const options = {
            method,
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': this.csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        };

        if (body !== undefined) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(body);
        }

        const response = await fetch(url, options);

        if (!response.ok) {
            throw new Error(await this.errorMessage(response));
        }

        return response.json();
    }

    async errorMessage(response) {
        try {
            const data = await response.json();
            if (data?.message) {
                return data.message;
            }
        } catch {
            // Response body was not JSON; fall back to the status.
        }

        return `Request failed (${response.status})`;
    }

    state() {
        return this.request('GET', '/api/state');
    }

    generateFixtures() {
        return this.request('POST', '/api/fixtures');
    }

    playNextWeek() {
        return this.request('POST', '/api/simulate/next');
    }

    playAllWeeks() {
        return this.request('POST', '/api/simulate/all');
    }

    reset() {
        return this.request('POST', '/api/reset');
    }

    updateMatch(matchId, homeGoals, awayGoals) {
        return this.request('PATCH', `/api/matches/${matchId}`, {
            home_goals: homeGoals,
            away_goals: awayGoals,
        });
    }
}
