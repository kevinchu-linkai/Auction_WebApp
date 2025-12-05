📘 Overview

This project implements a fully functional online auction system with support for:

Sellers creating and managing auctions

Buyers bidding on auctions

Watchlists, reviews, dashboards, recommendation engine

Advanced filtering, sorting, and dynamic auction state updates

Full relational DB schema following 3NF

Comprehensive SQL query library supporting every site feature

The system is designed for clarity, modularity, scalability and high data integrity.

🚀 Extra Functionalities

The platform includes a rich set of enhancements beyond basic auctioning. These contributions significantly improve user experience, transparency, and performance.

1. Auction Lifecycle Management

Sellers can edit, cancel, delete, and relist auctions depending on state rules:

Editable only when not-started

Cancel anytime

Relist only before end time

Delete removes auction + item + associated bids

This provides a complete auction lifecycle interface.

2. Full Photo Management

Sellers may:

Update, replace, delete auction photos

Store images either as file paths or Base64 blobs

System auto-checks for photo_base64 column and adds if missing

3. Seller Rating & Review System

Winning buyers can post:

⭐ Ratings (1–5)

📝 Written reviews

The platform displays:

Average seller rating

Total number of reviews

Recent comments

Reviews on the seller dashboard and listings

Only one review is allowed per auction.

4. Recommendation Engine (with Smart Caching)

A personalized recommendation system considers:

Bid patterns

Watchlist activity

Item similarity

Trending auctions

Urgency indicators

Because each computation can require 250+ DB calls, a RecommendationCache table significantly reduces query load:

Stores precomputed recommendation results

Invalidated automatically after relevant buyer actions

Improves page load time from seconds → sub-second

5. Watchlist Functionality

Buyers can bookmark auctions:

Add/remove using heart-toggle

Dedicated watchlist page

Count of watchers shown on each auction

6. Interactive Dashboards

Each major page displays its own summary:

Seller Dashboard (MyListings)

Auctions by state

Total revenue

Bid counts per listing

Buyer Dashboard (MyBids)

Bid activity

Won, lost, outbid statistics

Unreviewed sellers

Total amount spent

Watchlist Dashboard

Watched, active, ended items

7. Advanced Search, Filters & Sorting

Supports:

Keyword search

Category filtering

Status filtering

Price range

Sort by bids, price, dates

“Ending Soon” sorting

Highly optimized for large data sets

8. Dynamic Countdown Timers

Live countdowns show:

Time to auction start

Time to auction end

9. Status Badges & Visual Indicators

Clear colored tags for:

Winning, won, lost

Sold, expired, not-started, ongoing

Upcoming, ending soon

📊 Entity–Relationship Diagram & Assumptions

Includes:

Buyer & Seller as separate entities

Each auction linked to exactly one item and one seller

Items linked to one category

Auctions have states managed by system

Review allowed only from final winning buyer

Watchlist implemented as a many-to-many relationship

Recommendation cache as auxiliary performance table

(Insert ERD image or link if applicable)

🗄️ Database Schema

The project defines 9 tables, all in 3NF:

Buyer

Seller

Category

Item

Auction

Bid

AuctionWatch

Review

RecommendationCache

Each schema includes:

Variable definitions

Types & constraints

FK relationships

Cascade rules

Table purpose and design reasoning

See full details in the design report 

Design Report_v1.6

.

🔍 Third Normal Form (3NF) Verification

All tables satisfy 3NF:

Single primary key per table (except AuctionWatch composite key)

All attributes fully dependent on PK

No transitive dependencies

Foreign keys model relationships cleanly

Derived/cached data stored separately (RecommendationCache)

🧾 SQL Query Library

The project contains an extensive library of SQL queries (~150+), categorized by feature:

🔐 User Registration & Login

Seller/buyer account creation

Duplicate email checks

Secure password storage & retrieval

🏗️ Auction Creation & Editing

Item creation (Base64 or file path)

Category lookup & creation

Auction updates

Auto-state handling

🔍 Browsing & Searching

Filterable/sortable query with JOINs

Price range via HAVING

Bid/Watch counters

Category filters

💸 Bidding

Bid validation rules

Insert new bid

Invalidate recommendation cache

Outbid notification lookup

🧾 Seller Tools

My listings overview

Revenue calculation

Auction cancellation & relisting

Deletion workflow (bids → auction → item)

🎯 Buyer Tools

Total auctions bid on

Outbid auctions

Won auctions

Unreviewed sellers

Full bid records per auction

⭐ Review System

Fetch seller reviews

Review insertion

Rating summaries

🧠 Recommendation Engine

Cache insertion

Cache invalidation

Top-N scoring index-based queries