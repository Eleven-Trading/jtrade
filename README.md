# jtrade
jtrade is a simple and an open source tool to journalize your trades.

# The project
## Motivation
TraderVue and TraderSync are great and very powerful trading journals. However, after using them for a while, I was missing the following:
- Flexibility in creating graphs 
- Avoid being dependant on a specific software, company or solution provider
- Use, store, move and backup my data at my terms 
- Increase control over my financial data 

## Features
- Executions are concatenated into trades
- Add strategy and mistakes to better track and improve your trades
- Add screenshot of stock graph
- Add link to your trade video 

## Screenshots

Include logo/demo screenshot etc.

# Tech/framework used

## Built with
- Bootstrap 4 
- VueJS
- PHP


## Getting Started

### Prerequisites
#### Software and tools
- Gulp for building the project
- MYSQL database for storing your data
- AWS S3 bucket for storing images and videos
- (optional) Visualization tool like metabase to visualize your trades

#### Database structure
See MYSQLTableStructure

### Installation
- Add a .env file at the root of the project with the following variables:
	- mysqlhost = xxx
	- mysqlport = xxx
	- mysqluser = xxx
	- mysqlpassword = xxx
	- mysqldb = xxx
	- AWS_BUCKET = xxx
	- AWS_KEY = xxx
	- AWS_SECRKET = xxx
- Install dependencies (npm and composer)
- Run "gulp" in terminal and launch localhost:3000 in your browser

### Deployment
- This project includes a deployment file for deploying on PaaS Caprover
- More generally speaking, a php server to run the project

### How to use?
#### Upload data
- Download your executions from your trading platform (only TradeZero is currently supported)
- On jtrade, click on the download button on the upper right side
- Complete the desired information 
- Save to your database

#### Visualize your data (not provided)
- In your favorite data visualization tool (metabase, ...) explore your data and create your desired graphs
- Example of data and graphs: ARPP, profit ratio, etc.
- 

# Contribute
- As a trader and recreational developer I would love to get help improving this project. Things to work on and improve:
	- Add support to other trading platforms (currently, only TradeZero is supported)  
	- Clean and optimize code
	- Improve front end layout and develop new ideas 
	- And more...

# License
This project is open sourced under the GNU GPL v3 licence
