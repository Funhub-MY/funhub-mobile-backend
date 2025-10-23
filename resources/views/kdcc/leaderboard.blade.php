<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KDCC Leaderboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .category-tabs {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .category-tab {
            padding: 12px 30px;
            background: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .category-tab:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }

        .category-tab.active {
            background: #667eea;
            color: white;
        }

        .leaderboard-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .stats-bar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 20px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            display: block;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
            justify-content: center;
        }

        thead {
            background: #f8f9fa;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        th.center, td.center {
            text-align: center;
        }

        tbody tr {
            border-bottom: 1px solid #e9ecef;
            transition: background 0.2s ease;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        td {
            padding: 15px;
            color: #495057;
        }

        .rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .rank-1 {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: white;
            box-shadow: 0 4px 8px rgba(255, 215, 0, 0.3);
        }

        .rank-2 {
            background: linear-gradient(135deg, #C0C0C0, #808080);
            color: white;
            box-shadow: 0 4px 8px rgba(192, 192, 192, 0.3);
        }

        .rank-3 {
            background: linear-gradient(135deg, #CD7F32, #8B4513);
            color: white;
            box-shadow: 0 4px 8px rgba(205, 127, 50, 0.3);
        }

        .rank-other {
            background: #e9ecef;
            color: #495057;
        }

        .team-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .team-image {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .team-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }

        .team-name {
            font-weight: 600;
            font-size: 1.1rem;
            color: #212529;
            flex: 1;
            min-width: 0;
        }

        .vote-count {
            font-size: 1.2rem;
            font-weight: bold;
            color: #667eea;
        }

        .vote-bar {
            background: #e9ecef;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }

        .vote-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
        }

        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .no-data-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.8rem;
            }

            .stats-bar {
                padding: 15px;
            }

            .stat-value {
                font-size: 1.5rem;
            }

            th, td {
                padding: 10px;
                font-size: 0.9rem;
            }

            .team-image {
                width: 40px;
                height: 40px;
            }

            .team-name {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏆 KDCC Leaderboard</h1>
            <p>Total Teams: {{ $allTeams->count() }} | Total Votes: {{ $totalVotes }}</p>
        </div>

        <div class="category-tabs">
            <a href="{{ route('kdcc.leaderboard', ['category' => 1]) }}" style="text-decoration: none;">
                <button class="category-tab {{ request('category') == 1 ? 'active' : '' }}">
                    Under 17
                </button>
            </a>
            <a href="{{ route('kdcc.leaderboard', ['category' => 2]) }}" style="text-decoration: none;">
                <button class="category-tab {{ request('category') == 2 ? 'active' : '' }}">
                    Open
                </button>
            </a>
        </div>

        <div class="leaderboard-card">
            @if($teams->isNotEmpty())
                <div class="stats-bar">
                    <div class="stat-item">
                        <span class="stat-value">{{ $teams->count() }}</span>
                        <span class="stat-label">Teams</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value">{{ $teams->sum('vote_count') }}</span>
                        <span class="stat-label">Total Votes</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value">{{ $teams->first()->vote_count ?? 0 }}</span>
                        <span class="stat-label">Top Votes</span>
                    </div>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th class="center">Rank</th>
                                <th class="center">Team</th>
                                <th class="center">Votes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $maxVotes = $teams->max('vote_count') ?: 1;
                            @endphp
                            @foreach($teams as $index => $team)
                                <tr>
                                    <td class="center">
                                        <span class="rank-badge rank-{{ $index + 1 <= 3 ? $index + 1 : 'other' }}">
                                            {{ $index + 1 }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="team-info">
                                            <div class="team-image">
                                                <img src="{{ $team->image_url }}" alt="{{ $team->name }}">
                                            </div>
                                            <span class="team-name">{{ $team->name }}</span>
                                        </div>
                                    </td>
                                    <td class="center">
                                        <span class="vote-count">{{ number_format($team->vote_count) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="no-data">
                    <div class="no-data-icon">📊</div>
                    <h2>No teams found</h2>
                    <p>There are no teams in this category yet.</p>
                </div>
            @endif
        </div>
    </div>
</body>
</html>