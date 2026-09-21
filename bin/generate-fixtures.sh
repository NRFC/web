#!/bin/bash

# generate_fixtures.sh
# Generates sample fixture data for NRFC Fixtures plugin

OUTPUT_FILE="sample_fixtures.csv"
TEAMS=(
    "1st XV"
    "Lions"
    "Women XV"
    "Boys Senior Academy"
    "Boys Junior Academy"
    "Under 15 Boys"
    "Under 14 Boys"
    "Under 13 Boys"
    "Girls Senior Academy"
    "Girls Junior Academy"
    "Under 14 Girls"
    "Under 12 Girls"
    "Minis"
)

OPPOSING_CLUBS=(
    "Beccles"
    "Braintree"
    "Brentwood"
    "Bury"
    "Cambridge"
    "Chelmsford"
    "Colchester"
    "Crusaders"
    "Diss"
    "Eton Manor"
    "F&S Barbarians"
    "Fakenham"
    "HAC"
    "Harlow"
    "Haverhill"
    "Holt"
    "Ipswich"
    "Lakenham Union"
    "Lowestoft & Yarmouh"
    "Newmarket"
    "North Walsham"
    "Rochford Hundred"
    "Shelford"
    "Southwold"
    "Stowmarket"
    "UEA"
    "Wanstead"
    "West Norfolk"
    "Wisbech"
    "Woodbridge"
    "Woodford"
    "Wymondham"
)

OPPOSING_TEAMS=(
    "1st XV"
    "2nd XV"
    "3rd XV"
    "4th XV"
    "Development XV"
)

COMPETITIONS=(
    "League"
    "Cup"
    "Friendly"
    "Festival"
    "Other"
)

VENUES=("Home" "Away")

# Function to get next Saturday
get_next_saturday() {
    local start_date=$1
    local offset=$2
    local weeks=$3

    # Find the next Saturday from start date
    local day_of_week=$(date -d "$start_date" +%u)  # 1=Mon, 7=Sun
    local days_to_saturday=$(( (6 - $day_of_week + 7) % 7 ))
    if [ $days_to_saturday -eq 0 ]; then
        days_to_saturday=7
    fi

    # Add weeks offset
    local total_days=$(( ($weeks * 7) + $days_to_saturday ))
    date -d "$start_date +${total_days} days" +%Y-%m-%d
}

# Function to get next Sunday
get_next_sunday() {
    local start_date=$1
    local offset=$2
    local weeks=$3

    # Find the next Sunday from start date
    local day_of_week=$(date -d "$start_date" +%u)  # 1=Mon, 7=Sun
    local days_to_sunday=$(( (7 - $day_of_week + 7) % 7 ))
    if [ $days_to_sunday -eq 0 ]; then
        days_to_sunday=7
    fi

    # Add weeks offset
    local total_days=$(( ($weeks * 7) + $days_to_sunday ))
    date -d "$start_date +${total_days} days" +%Y-%m-%d
}

# Function to generate random time
generate_time() {
    local hour=$(( 10 + $RANDOM % 6 ))  # 10am to 3pm
    local minute=$(( ($RANDOM % 4) * 15 ))  # 0, 15, 30, or 45
    printf "%02d:%02d" $hour $minute
}

# Function to get random element from array
get_random() {
    local arr=("${!1}")
    local idx=$(( RANDOM % ${#arr[@]} ))
    echo "${arr[$idx]}"
}

# Create CSV header
echo "date,team,opposing_club,opposing_team,competition_type,kick_off_time,venue,notes" > "$OUTPUT_FILE"

# Start date for fixtures (next month)
START_DATE=$(date -d "-4 months" +%Y-%m-01)

# Generate fixtures for each team
for ((week=0; week<20; week++)); do  # 20 weeks of fixtures
    # 1st XV plays on Saturday
    date_1st=$(get_next_saturday "$START_DATE" 0 $week)
    time_1st="15:00"  # Traditional 3pm kickoff for 1st XV
    opposition_1st=$(get_random OPPOSING_CLUBS[@])

    echo "$date_1st,1st XV,$opposition_1st,1st XV,League,$time_1st,Away,Match week $((week+1))" >> "$OUTPUT_FILE"

    # Lions play on Saturday (alternate weeks from 1st XV)
    if [ $((week % 2)) -eq 0 ]; then
        date_lions=$date_1st
        time_lions="14:30"
        opposition_lions=$(get_random OPPOSING_CLUBS[@])

        echo "$date_lions,Lions,$opposition_lions,2nd XV,Friendly,$time_lions,Home,Training match" >> "$OUTPUT_FILE"
    fi

    # All other teams play on Sunday
    for team in "${TEAMS[@]:2}"; do  # Skip 1st XV and Lions
        date_sunday=$(get_next_sunday "$START_DATE" 0 $week)
        time_sunday=$(generate_time)
        opposition=$(get_random OPPOSING_CLUBS[@])
        opp_team=$(get_random OPPOSING_TEAMS[@])
        competition=$(get_random COMPETITIONS[@])
        venue=$(get_random VENUES[@])

        # Special handling for different team types
        notes=""
        case $team in
            *"Women"*|*"Girls"*)
                notes="Women's/Girls fixture"
                ;;
            *"Boys"*|*"Under"*)
                notes="Youth fixture"
                ;;
            "Minis")
                notes="Mini rugby festival"
                competition="Festival"
                time_sunday="10:30"
                ;;
        esac

        echo "$date_sunday,$team,$opposition,$opp_team,$competition,$time_sunday,$venue,$notes" >> "$OUTPUT_FILE"
    done
done

# Generate some midweek fixtures
for ((i=0; i<5; i++)); do
    date_midweek=$(date -d "$START_DATE +$((i*14)) days" +%Y-%m-%d)
    day_of_week=$(date -d "$date_midweek" +%u)

    # Make it a Wednesday
    if [ $day_of_week -ne 3 ]; then
        date_midweek=$(date -d "$date_midweek +$(( (3 - $day_of_week + 7) % 7 )) days" +%Y-%m-%d)
    fi

    time_midweek="19:30"

    # Midweek training matches
    echo "$date_midweek,Lions,Holt,2nd XV,Friendly,$time_midweek,Home,Midweek training" >> "$OUTPUT_FILE"
    echo "$date_midweek,Boys Senior Academy,Wisbech,Development XV,Friendly,$time_midweek,Home,Development match" >> "$OUTPUT_FILE"
done

echo "Generated $OUTPUT_FILE with sample fixture data"
echo "Total fixtures generated: $(wc -l < "$OUTPUT_FILE" | xargs)"