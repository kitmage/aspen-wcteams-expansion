# Aspen Team Seats Expansion

Aspen Team Seats Expansion is a small WordPress plugin that adds shortcodes for displaying or hiding content based on the current user's Teams for WooCommerce Memberships team status.

The plugin assumes each WordPress account is associated with only one team and treats that team as the team where the current user is an owner, manager, or member.

## Requirements

- WordPress
- WooCommerce Memberships
- Teams for WooCommerce Memberships

## Shortcodes

### Display the current user's team name

Use `[teamx_name]` anywhere shortcodes are supported to display the current user's team name.

```text
Your team: [teamx_name]
```

If the current visitor is not logged in or is not associated with a team, the shortcode outputs nothing.

### Display the current user's team status

Use `[teamx_status]` anywhere shortcodes are supported to display the current user's team user-membership status, formatted for display. For example, a stored `wc_user_membership` status of `wcm-paused` displays as `Paused`.

```text
Your team membership is currently: [teamx_status]
```

If the current visitor is not logged in or is not associated with a team membership status, the shortcode outputs nothing.

### Restrict content by team membership plan or status

Use `[teamx_restrict]` to show or hide enclosed content based on whether the current user's team matches the provided membership plan and/or membership status expressions.

```text
[teamx_restrict plan="111" mode="show"]
This content is shown only to users whose team has an active membership for plan 111.
[/teamx_restrict]
```

The shortcode supports these attributes:

- `plan` — membership plan ID expression, such as `111`, `111,222`, or `111+222`.
- `status` — membership status expression, such as `active`, `paused`, or `!canceled`. The plugin normalizes WordPress post statuses such as `wcm-paused` to `paused` before matching.
- `mode` — whether matching content should be shown or hidden.

The `mode` attribute supports:

- `show` — display the enclosed content when the plan expression matches.
- `hide` — hide the enclosed content when the plan expression matches.

If `mode` is omitted, the shortcode defaults to `show`.

## Plan expression logic

The `plan` and `status` attributes support simple boolean logic:

| Operator | Meaning | Example |
| --- | --- | --- |
| `,` | OR | `111,222` matches plan 111 or plan 222. |
| `+` | AND | `111+222` matches only when both plans 111 and 222 are active. |
| `!` | NOT | `!333` matches when plan 333 is not active; `!canceled` matches when the status is not canceled. |

### Show examples

Show content to users whose team has an active membership for plan 111 or 222:

```text
[teamx_restrict plan="111,222" mode="show"]
Show this to members of plans 111 or 222.
[/teamx_restrict]
```

Show content only when the user's team has both plan 111 and plan 222:

```text
[teamx_restrict plan="111+222" mode="show"]
Show this to teams that have both plan 111 and plan 222.
[/teamx_restrict]
```

Show content to users whose team does not have plan 333:

```text
[teamx_restrict plan="!333" mode="show"]
Show this to teams that do not have plan 333.
[/teamx_restrict]
```


Show content to users whose team membership is paused:

```text
[teamx_restrict status="paused" mode="show"]
Show this to teams with a paused membership.
[/teamx_restrict]
```

Show content to users whose team membership is not canceled:

```text
[teamx_restrict status="!canceled" mode="show"]
Show this to teams whose membership status is not canceled.
[/teamx_restrict]
```

Show content to users whose team has plan 111 and a paused status:

```text
[teamx_restrict plan="111" status="paused" mode="show"]
Show this to plan 111 teams whose membership is paused.
[/teamx_restrict]
```

### Hide examples

Hide content from users whose team has an active membership for plan 111 or whose team does not have plan 333:

```text
[teamx_restrict plan="111,!333" mode="hide"]
Hide this from members who have plan 111, or members who do not have plan 333.
[/teamx_restrict]
```

Hide content from users whose team has both plan 111 and plan 222:

```text
[teamx_restrict plan="111+222" mode="hide"]
Hide this from teams that have both plan 111 and plan 222.
[/teamx_restrict]
```

## Notes for content authors

- Use membership plan ID numbers in the `plan` attribute, not plan names.
- Use membership status slugs from the related `wc_user_membership` record without the `wcm-` prefix in the `status` attribute, such as `active`, `paused`, or `canceled`.
- Commas are evaluated as OR groups.
- Plus signs are evaluated within each OR group as AND conditions.
- Exclamation points negate a single plan ID.
- Enclosed shortcode content is processed with `do_shortcode`, so nested shortcodes can be used inside `[teamx_restrict]`.
