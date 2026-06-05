import { Component } from '../lib/Component.js';
import { el } from '../lib/dom.js';

/**
 * Championship prediction panel: each team's title chance as a percentage
 * with a proportional Bootstrap progress bar, ordered most to least likely.
 *
 * Predictions only open in the season's final weeks; until then the panel
 * shows a locked message.
 */
export class Predictions extends Component {
    render() {
        return el('div', { class: 'card shadow-sm' },
            el('div', { class: 'card-header bg-dark text-white fw-semibold' }, 'Championship Predictions %'),
            this.props.status === 'locked' ? this.locked() : this.list(),
        );
    }

    locked() {
        return el('div', { class: 'card-body text-center text-muted py-4 small' },
            'Predictions open in the last weeks of the season.',
        );
    }

    list() {
        const { predictions, hasPlayed } = this.props;

        const ordered = [...predictions].sort((a, b) => b.percentage - a.percentage);

        const items = ordered.map((entry) => el('li', { class: 'list-group-item' },
            el('div', { class: 'd-flex justify-content-between align-items-center' },
                el('span', {}, entry.name),
                el('span', { class: 'fw-bold' }, `${formatPercentage(entry.percentage)}%`),
            ),
            hasPlayed
                ? el('div', { class: 'progress mt-2', style: 'height:4px' },
                    el('div', {
                        class: 'progress-bar',
                        role: 'progressbar',
                        style: `width:${entry.percentage}%`,
                    }),
                )
                : null,
        ));

        return el('ul', { class: 'list-group list-group-flush' }, ...items);
    }
}

function formatPercentage(value) {
    return Number.isInteger(value) ? `${value}` : value.toFixed(1);
}
